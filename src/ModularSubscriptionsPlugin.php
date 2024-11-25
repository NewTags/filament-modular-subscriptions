<?php

namespace HoceineEl\FilamentModularSubscriptions;

use Closure;
use Filament\Contracts\Plugin;
use Filament\Panel;
use HoceineEl\FilamentModularSubscriptions\Pages\TenantSubscription;
use Outerweb\FilamentTranslatableFields\Filament\Plugins\FilamentTranslatableFieldsPlugin;
use Filament\Support\Facades\FilamentView;
use Filament\Notifications\Notification;
use Filament\Notifications\Actions\Action as NotificationAction;
use Illuminate\Support\Facades\Cache;
use Filament\Facades\Filament;
use Filament\View\PanelsRenderHook;

class ModularSubscriptionsPlugin implements Plugin
{
    protected bool $hasSubscriptionStats = true;

    protected bool $onTenantPanel = false;

    public static function make(): static
    {
        return app(static::class);
    }

    public function getId(): string
    {
        return 'filament-modular-subscriptions';
    }

    public function register(Panel $panel): void
    {
        if (! $this->onTenantPanel) {
            $panel
                ->plugin(FilamentTranslatableFieldsPlugin::make())
                ->resources(config('filament-modular-subscriptions.resources'));
        } else {
            $panel
                ->pages([
                    TenantSubscription::class,
                ]);

            // Register the subscription status hook
            FilamentView::registerRenderHook(
                PanelsRenderHook::PAGE_START,
                fn(): string => $this->renderSubscriptionAlerts()
            );
        }

        if ($this->hasSubscriptionStats) {
            $panel->widgets([
                // SubscriptionStatsWidget::class,
            ]);
        }
    }

    public function boot(Panel $panel): void
    {
        //
    }

    public function onTenantPanel(Closure | bool $condition = true): static
    {
        $this->onTenantPanel = $condition instanceof Closure ? $condition() : $condition;

        return $this;
    }

    public function subscriptionStats(bool $condition = true): static
    {
        $this->hasSubscriptionStats = $condition;

        return $this;
    }

    public function hasSubscriptionStats(): bool
    {
        return $this->hasSubscriptionStats;
    }

    public static function get(): static
    {
        return filament(app(static::class)->getId());
    }

    protected function renderSubscriptionAlerts(): string
    {
        // Early return if no tenant
        if (!$this->onTenantPanel) {
            return '';
        }

        // Cache subscription status checks for 5 minutes per tenant
        $cacheKey = 'subscription_alerts_' . filament()->getTenant()->id;
        $alerts = Cache::remember($cacheKey, now()->addMinutes(30), function () {
            $alerts = [];
            $tenant = filament()->getTenant();
            $subscription = $tenant->activeSubscription();

            if (!$subscription) {
                $alerts[] = [
                    'type' => 'warning',
                    'title' => __('filament-modular-subscriptions::fms.tenant_subscription.no_active_subscription'),
                    'body' => __('filament-modular-subscriptions::fms.tenant_subscription.no_subscription_message'),
                    'action' => [
                        'label' => __('filament-modular-subscriptions::fms.tenant_subscription.select_plan'),
                        'url' => TenantSubscription::getUrl(),
                    ],
                ];
                return $alerts;
            }

            // Check subscription expiration
            if ($subscription->ends_at && $subscription->ends_at->isPast()) {
                $alerts[] = [
                    'type' => 'danger',
                    'title' => __('filament-modular-subscriptions::fms.status.expired'),
                    'body' => __('filament-modular-subscriptions::fms.messages.you_have_to_renew_your_subscription_to_use_this_module'),
                    'action' => [
                        'label' => __('filament-modular-subscriptions::fms.tenant_subscription.select_plan'),
                        'url' => TenantSubscription::getUrl(),
                    ],
                ];
                return $alerts;
            }

            // Check if subscription is ending soon (within 7 days)
            if ($subscription->ends_at && $subscription->daysLeft() <= 7) {
                $alerts[] = [
                    'type' => 'warning',
                    'title' => __('filament-modular-subscriptions::fms.messages.subscription_ending_soon'),
                    'body' => __('filament-modular-subscriptions::fms.tenant_subscription.days_left') . ': ' . $subscription->daysLeft(),
                    'action' => [
                        'label' => __('filament-modular-subscriptions::fms.tenant_subscription.select_plan'),
                        'url' => TenantSubscription::getUrl(),
                    ],
                ];
            }

            // Check module limits and suggest next plan
            foreach ($subscription->moduleUsages as $moduleUsage) {
                $module = $moduleUsage->module;
                $limit = $module->planModules()
                    ->where('plan_id', $subscription->plan_id)
                    ->first()
                    ?->limit;

                if (!$tenant->canUseModule($module->class)) {
                    $nextPlan = $this->getNextSuitablePlan($subscription, $module);
                    $alerts[] = [
                        'type' => 'warning',
                        'title' => __('filament-modular-subscriptions::fms.messages.module_limit_warning'),
                        'body' => sprintf(
                            '%s: %d/%d (%d%%)',
                            $module->getName(),
                            $moduleUsage->usage,
                            $limit,
                            100
                        ),
                        'action' => [
                            'label' => __('filament-modular-subscriptions::fms.messages.upgrade_now'),
                            'url' => TenantSubscription::getUrl(),
                        ],
                    ];
                }
            }

            return $alerts;
        });

        return view('filament-modular-subscriptions::components.subscription-alerts', [
            'alerts' => $alerts,
        ])->render();
    }

    protected function getNextSuitablePlan($subscription, $module)
    {
        $planModel = config('filament-modular-subscriptions.models.plan');
        $currentPlan = $subscription->plan;

        // Find a plan with a higher limit for the module or a pay-as-you-go plan
        return $planModel::where('id', '!=', $currentPlan->id)
            ->where(function ($query) use ($module, $currentPlan) {
                $query->whereHas('planModules', function ($query) use ($module, $currentPlan) {
                    $query->where('module_id', $module->id)
                        ->where('limit', '>', $module->planModules()->where('plan_id', $currentPlan->id)->first()->limit);
                })
                    ->orWhere('is_pay_as_you_go', true);
            })
            ->orderBy('price', 'asc')
            ->first();
    }
}
