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
use Illuminate\Database\Eloquent\Model;

class ModularSubscriptionsPlugin implements Plugin
{
    protected bool $hasSubscriptionStats = true;

    protected bool $onTenantPanel = false;

    protected ?Model $tenant = null;

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
                ])->bootUsing(function () {
                    $this->tenant = filament()->getTenant();
                    FilamentView::registerRenderHook(
                        PanelsRenderHook::PAGE_START,
                        fn(): string => $this->renderSubscriptionAlerts()
                    );
                });
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
        if (!$this->onTenantPanel || !$this->tenant) {
            return '';
        }

        $cacheKey = 'subscription_alerts_' . $this->tenant->id;

        $alerts = Cache::remember($cacheKey, now()->addMinutes(30), function () {
            $alerts = [];
            $tenant = $this->tenant;
            $subscription = $tenant->activeSubscription();

            if (!$subscription) {
                $alerts[] = $this->createAlert(
                    'warning',
                    __('filament-modular-subscriptions::fms.tenant_subscription.no_active_subscription'),
                    __('filament-modular-subscriptions::fms.tenant_subscription.no_subscription_message'),
                    __('filament-modular-subscriptions::fms.tenant_subscription.select_plan')
                );
                return $alerts;
            }

            if ($subscription->ends_at && $subscription->ends_at->isPast()) {
                $alerts[] = $this->createAlert(
                    'danger',
                    __('filament-modular-subscriptions::fms.status.expired'),
                    __('filament-modular-subscriptions::fms.messages.you_have_to_renew_your_subscription_to_use_this_module'),
                    __('filament-modular-subscriptions::fms.tenant_subscription.select_plan')
                );
                return $alerts;
            }

            if ($subscription->ends_at && $subscription->daysLeft() <= 7) {
                $alerts[] = $this->createAlert(
                    'warning',
                    __('filament-modular-subscriptions::fms.messages.subscription_ending_soon'),
                    __('filament-modular-subscriptions::fms.tenant_subscription.days_left') . ': ' . $subscription->daysLeft(),
                    __('filament-modular-subscriptions::fms.tenant_subscription.select_plan')
                );
            }

            foreach ($subscription->moduleUsages as $moduleUsage) {
                $module = $moduleUsage->module;
                $planModule = $module->planModules()
                    ->where('plan_id', $subscription->plan_id)
                    ->first();

                $limit = $planModule?->limit;

                if (!$tenant->canUseModule($module->class)) {
                    $nextPlan = $this->getNextSuitablePlan($subscription, $module);
                    $alerts[] = $this->createAlert(
                        'warning',
                        __('filament-modular-subscriptions::fms.messages.module_limit_warning'),
                        sprintf(
                            '%s: %d/%d (%d%%)',
                            $module->getName(),
                            $moduleUsage->usage,
                            $limit,
                            ($moduleUsage->usage / $limit) * 100
                        ),
                        __('filament-modular-subscriptions::fms.messages.upgrade_now')
                    );
                }
            }

            return $alerts;
        });

        return view('filament-modular-subscriptions::components.subscription-alerts', [
            'alerts' => $alerts,
        ])->render();
    }

    protected function createAlert(string $type, string $title, string $body, string $actionLabel): array
    {
        return [
            'type' => $type,
            'title' => $title,
            'body' => $body,
            'action' => [
                'label' => $actionLabel,
                'url' => TenantSubscription::getUrl(),
            ],
        ];
    }

    protected function getNextSuitablePlan($subscription, $module): ?Model
    {
        $planModel = config('filament-modular-subscriptions.models.plan');
        $currentPlan = $subscription->plan;
        $currentLimit = $module->planModules()
            ->where('plan_id', $currentPlan->id)
            ->first()?->limit ?? 0;

        return $planModel::where('id', '!=', $currentPlan->id)
            ->where(function ($query) use ($module, $currentLimit) {
                $query->whereHas('planModules', function ($query) use ($module, $currentLimit) {
                    $query->where('module_id', $module->id)
                        ->where('limit', '>', $currentLimit);
                })
                    ->orWhere('is_pay_as_you_go', true);
            })
            ->orderBy('price', 'asc')
            ->first();
    }
}
