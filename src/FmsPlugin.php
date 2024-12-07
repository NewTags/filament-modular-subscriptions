<?php

namespace HoceineEl\FilamentModularSubscriptions;

use Closure;
use Filament\Contracts\Plugin;
use Filament\Panel;
use Filament\Support\Facades\FilamentView;
use Filament\View\PanelsRenderHook;
use HoceineEl\FilamentModularSubscriptions\Pages\TenantSubscription;
use HoceineEl\FilamentModularSubscriptions\Enums\SubscriptionStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\View;
use Outerweb\FilamentTranslatableFields\Filament\Plugins\FilamentTranslatableFieldsPlugin;

class FmsPlugin implements Plugin
{
    private const ALERTS_CACHE_KEY = 'subscription_alerts_';

    private const ALERTS_CACHE_TTL = 30; // minutes

    public bool $hasSubscriptionStats = true;

    public bool $onTenantPanel = false;

    public static ?Closure $getTenantUsing = null;

    public ?Model $tenant = null;

    public array $cachedAlerts = [];

    public ?string $navigationGroup = null;
    public ?string $tenantNavigationGroup = null;
    public ?string $subscriptionNavigationLabel = null;

    public static function make(): static
    {
        return app(static::class);
    }

    public function getId(): string
    {
        return 'filament-modular-subscriptions';
    }

    public static function get(): static
    {
        return filament('filament-modular-subscriptions');
    }

    public function onTenantPanel(Closure | bool $condition = true): static
    {
        $this->onTenantPanel = $condition instanceof Closure ? $condition() : $condition;
        return $this;
    }

    public function getTenantUsing(?Closure $callback = null): Closure|static
    {
        static::$getTenantUsing = $callback;

        return $this;
    }

    public static function getTenant(): mixed
    {
        return app()->call(static::$getTenantUsing ?? function () {
            return filament()->getTenant();
        });
    }

    public function register(Panel $panel): void
    {
        if (! $this->onTenantPanel) {
            $panel
                ->plugin(FilamentTranslatableFieldsPlugin::make())
                ->resources(config('filament-modular-subscriptions.resources'));
        } else {
            $panel
                ->pages([TenantSubscription::class])
                ->bootUsing(function () {
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

    public function boot(Panel $panel): void {}



    public function subscriptionStats(bool $condition = true): static
    {
        $this->hasSubscriptionStats = $condition;

        return $this;
    }

    public function hasSubscriptionStats(): bool
    {
        return $this->hasSubscriptionStats;
    }


    protected function renderSubscriptionAlerts(): string
    {
        if (! $this->onTenantPanel) {
            return '';
        }

        $tenant = self::getTenant();
        if (! $tenant) {
            return '';
        }

        $alerts = $this->getAlertsFromCache($tenant);

        return View::make('filament-modular-subscriptions::components.subscription-alerts', [
            'alerts' => $alerts,
        ])->render();
    }

    protected function getAlertsFromCache(Model $tenant): array
    {
        $cacheKey = self::ALERTS_CACHE_KEY . $tenant->id;

        return Cache::remember($cacheKey, now()->addHours(5), function () use ($tenant) {
            return $this->generateAlerts($tenant);
        });
    }

    protected function generateAlerts(Model $tenant): array
    {
        $alerts = [];
        $subscription = $tenant->subscription;

        if (! $subscription || $subscription->status === SubscriptionStatus::CANCELLED) {
            return [$this->createNoSubscriptionAlert()];
        }



        if ($subscription->status === SubscriptionStatus::ON_HOLD) {
            return [$this->createOnHoldAlert()];
        }

        if ($subscription->status === SubscriptionStatus::PENDING_PAYMENT) {
            return [$this->createPendingPaymentAlert()];
        }

        if ($this->isSubscriptionExpired($subscription)) {
            return [$this->createExpiredSubscriptionAlert()];
        }

        if ($this->isSubscriptionEndingSoon($subscription)) {
            $alerts[] = $this->createEndingSoonAlert($subscription);
        }

        $alerts = array_merge($alerts, $this->generateModuleUsageAlerts($subscription, $tenant));

        return $alerts;
    }

    protected function isSubscriptionExpired($subscription): bool
    {
        return $subscription->ends_at && $subscription->ends_at->isPast();
    }

    protected function isSubscriptionEndingSoon($subscription): bool
    {
        return $subscription->ends_at && $subscription->daysLeft() <= 7;
    }

    protected function generateModuleUsageAlerts($subscription, $tenant): array
    {
        $alerts = [];
        $moduleUsages = $subscription->moduleUsages()->with(['module.planModules' => function ($query) use ($subscription) {
            $query->where('plan_id', $subscription->plan_id);
        }])->get();

        foreach ($moduleUsages as $moduleUsage) {
            $module = $moduleUsage->module;
            $planModule = $module->planModules->first();
            $limit = $planModule?->limit;

            if (! $tenant->canUseModule($module->class)) {
                $alerts[] = $this->createModuleLimitAlert($module, $moduleUsage, $limit);
            }
        }

        return $alerts;
    }

    protected function createNoSubscriptionAlert(): array
    {
        return $this->createAlert(
            'warning',
            __('filament-modular-subscriptions::fms.tenant_subscription.no_active_subscription'),
            __('filament-modular-subscriptions::fms.tenant_subscription.no_subscription_message'),
            __('filament-modular-subscriptions::fms.tenant_subscription.select_plan')
        );
    }

    protected function createExpiredSubscriptionAlert(): array
    {
        return $this->createAlert(
            'danger',
            __('filament-modular-subscriptions::fms.statuses.expired'),
            __('filament-modular-subscriptions::fms.tenant_subscription.you_have_to_renew_your_subscription'),
            __('filament-modular-subscriptions::fms.tenant_subscription.pay_invoice')
        );
    }

    protected function createOnHoldAlert(): array
    {
        return $this->createAlert(
            'warning',
            __('filament-modular-subscriptions::fms.tenant_subscription.subscription_on_hold'),
            __('filament-modular-subscriptions::fms.tenant_subscription.subscription_on_hold_message'),
            __('filament-modular-subscriptions::fms.tenant_subscription.select_plan')
        );
    }

    protected function createPendingPaymentAlert(): array
    {
        return $this->createAlert(
            'warning',
            __('filament-modular-subscriptions::fms.tenant_subscription.subscription_pending_payment'),
            __('filament-modular-subscriptions::fms.tenant_subscription.subscription_pending_payment_message'),
            __('filament-modular-subscriptions::fms.tenant_subscription.pay_invoice')
        );
    }

    protected function createEndingSoonAlert($subscription): array
    {
        return $this->createAlert(
            'warning',
            __('filament-modular-subscriptions::fms.tenant_subscription.subscription_ending_soon'),
            __('filament-modular-subscriptions::fms.tenant_subscription.days_left') . ': ' . $subscription->daysLeft(),
            __('filament-modular-subscriptions::fms.tenant_subscription.select_plan')
        );
    }

    protected function createModuleLimitAlert($module, $moduleUsage, $limit): array
    {
        return $this->createAlert(
            'danger',
            __('filament-modular-subscriptions::fms.tenant_subscription.you_have_reached_the_limit_of_this_module'),
            sprintf(
                '%s: %d/%d (%d%%)',
                $module->getName(),
                $moduleUsage->usage,
                $limit,
                ($moduleUsage->usage / $limit) * 100
            ),
            __('filament-modular-subscriptions::fms.tenant_subscription.upgrade_now')
        );
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

    public function navigationGroup(string | Closure $label): static
    {
        $this->navigationGroup = $label instanceof Closure ? $label() : $label;
        return $this;
    }

    public function tenantNavigationGroup(string | Closure $label): static
    {
        $this->tenantNavigationGroup = $label instanceof Closure ? $label() : $label;
        return $this;
    }

    public function subscriptionNavigationLabel(string | Closure $label): static
    {
        $this->subscriptionNavigationLabel = $label instanceof Closure ? $label() : $label;
        return $this;
    }

    public function getNavigationGroup(): string
    {
        return $this->navigationGroup ?? __('filament-modular-subscriptions::fms.menu_group.subscription_management');
    }

    public function getTenantNavigationGroup(): string
    {
        return $this->tenantNavigationGroup ?? __('filament-modular-subscriptions::fms.tenant_subscription.subscription_navigation_label');
    }

    public function getSubscriptionNavigationLabel(): string
    {
        return $this->subscriptionNavigationLabel ?? __('filament-modular-subscriptions::fms.tenant_subscription.your_subscription');
    }

    public function isOnTenantPanel(): bool
    {
        return $this->onTenantPanel;
    }
}
