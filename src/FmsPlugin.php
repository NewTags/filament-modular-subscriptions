<?php

namespace NewTags\FilamentModularSubscriptions;

use Closure;
use Filament\Contracts\Plugin;
use Filament\Navigation\MenuItem;
use Filament\Panel;
use Filament\Support\Colors\Color;
use Filament\Support\Facades\FilamentView;
use Filament\View\PanelsRenderHook;
use NewTags\FilamentModularSubscriptions\Pages\TenantSubscription;
use NewTags\FilamentModularSubscriptions\Enums\SubscriptionStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\View;
use NewTags\FilamentModularSubscriptions\Models\Module;
use NewTags\FilamentModularSubscriptions\Models\Subscription;
use NewTags\FilamentModularSubscriptions\Widgets\ModuleUsageWidget;
use Outerweb\FilamentTranslatableFields\Filament\Plugins\FilamentTranslatableFieldsPlugin;

class FmsPlugin implements Plugin
{
    private const ALERTS_CACHE_KEY = 'subscription_alerts_';

    private const ALERTS_CACHE_TTL = 30; // minutes

    public bool $hasSubscriptionStats = true;

    public bool $onTenantPanel = false;

    public static ?Closure $getTenantUsing = null;

    public static ?Closure $afterInvoicePaidUsing = null;

    public ?Model $tenant = null;

    public array $cachedAlerts = [];

    public ?string $navigationGroup = null;
    public ?string $tenantNavigationGroup = null;
    public ?string $subscriptionNavigationLabel = null;
    public bool | Closure $subscriptionPageInTenantMenu = true;
    public bool | Closure $subscriptionPageInUserMenu = false;
    public static bool | Closure $subscriptionPageInNavigationMenu = false;

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

    public function afterInvoicePaid(?Closure $callback = null): static
    {
        static::$afterInvoicePaidUsing = $callback;

        return $this;
    }

    public static function runAfterInvoicePaid(Model $invoice): mixed
    {
        if (static::$afterInvoicePaidUsing === null) {
            return null;
        }

        return app()->call(static::$afterInvoicePaidUsing, ['invoice' => $invoice]);
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
                ->tenantMenuItems([
                    MenuItem::make()
                        ->label(fn() => $this->getSubscriptionNavigationLabel())
                        ->url(fn() => TenantSubscription::getUrl())
                        ->color(fn() => Color::Emerald)
                        ->visible(fn() => $this->subscriptionPageInTenantMenu && $this->canSeeTenantSubscription())
                        ->icon('heroicon-o-credit-card'),
                ])

                ->widgets([
                    ModuleUsageWidget::class,
                ])
                ->bootUsing(function () {
                    FilamentView::registerRenderHook(
                        PanelsRenderHook::BODY_START,
                        fn(): string => $this->canSeeTenantSubscription() ? $this->renderSubscriptionAlerts() : ''
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

    public static function canSeeTenantSubscription(): bool
    {
        if (!auth()->check()) {
            return false;
        }
        $auth =  auth()->user();
        return  cache()->store('file')->remember(
            'tenant_subscription_nav_' . $auth->id . '_' . FmsPlugin::getTenant()->id,
            now()->addMinutes(60),
            fn() => FmsPlugin::getTenant()->admins()->where('users.id', $auth->id)->exists()
        );
    }

    public function subscriptionPageInTenantMenu(bool | Closure $condition = true): static
    {
        $this->subscriptionPageInTenantMenu = $condition instanceof Closure ? $condition() : $condition;

        return $this;
    }
    public function subscriptionPageInUserMenu(bool | Closure $condition = true): static
    {
        $this->subscriptionPageInUserMenu = $condition instanceof Closure ? $condition() : $condition;

        return $this;
    }
    public static function subscriptionPageInNavigationMenu(bool | Closure $condition = true): static
    {
        static::$subscriptionPageInNavigationMenu = $condition instanceof Closure ? $condition() : $condition;

        return new static();
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

        return Cache::remember($cacheKey, now()->addMinutes(30), function () use ($tenant) {
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

        if ($subscription->isExpired()) {
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
        return $subscription->isExpired();
    }

    protected function isSubscriptionEndingSoon($subscription): bool
    {
        return $subscription->ends_at && $subscription->daysLeft() <= 3;
    }

    protected function generateModuleUsageAlerts($subscription, $tenant): array
    {
        $alerts = [];
        $modules = $subscription->plan->modules;
        foreach ($modules as $module) {
            $limit = $subscription->plan->moduleLimit($module);
            if (! $tenant->canUseModule($module->class) && $limit > 0) {
                $alerts[] = $this->createModuleLimitAlert($module, $subscription, $limit);
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
            __('filament-modular-subscriptions::fms.tenant_subscription.select_plan'),
            'plans'
        );
    }

    protected function createExpiredSubscriptionAlert(): array
    {
        return $this->createAlert(
            'danger',
            __('filament-modular-subscriptions::fms.statuses.expired'),
            __('filament-modular-subscriptions::fms.tenant_subscription.you_have_to_renew_your_subscription'),
            __('filament-modular-subscriptions::fms.tenant_subscription.renew_subscription'),
            'invoices'
        );
    }

    protected function createOnHoldAlert(): array
    {
        return $this->createAlert(
            'warning',
            __('filament-modular-subscriptions::fms.tenant_subscription.subscription_on_hold'),
            __('filament-modular-subscriptions::fms.tenant_subscription.subscription_on_hold_message'),
            __('filament-modular-subscriptions::fms.tenant_subscription.pay_invoice'),
            'invoices'
        );
    }

    protected function createPendingPaymentAlert(): array
    {
        return $this->createAlert(
            'warning',
            __('filament-modular-subscriptions::fms.tenant_subscription.subscription_pending_payment'),
            __('filament-modular-subscriptions::fms.tenant_subscription.subscription_pending_payment_message'),
            __('filament-modular-subscriptions::fms.tenant_subscription.pay_invoice'),
            'invoices'
        );
    }

    protected function createEndingSoonAlert($subscription): array
    {
        return $this->createAlert(
            'warning',
            __('filament-modular-subscriptions::fms.tenant_subscription.subscription_ending_soon'),
            __('filament-modular-subscriptions::fms.tenant_subscription.days_left') . ': ' . $subscription->daysLeft(),
            __('filament-modular-subscriptions::fms.tenant_subscription.select_plan'),
            'plans'
        );
    }

    protected function createModuleLimitAlert(Module $module, Subscription $subscription, $limit = null): array
    {
        $moduleInstance = $module->getInstance();
        $label = $moduleInstance->getLabel();
        $usage = $moduleInstance->calculateUsage($subscription);
        $limit = $limit ?? $subscription->plan->moduleLimit($module);
        $percentage = ($usage / $limit) * 100;
        return $this->createAlert(
            'danger',
            __('filament-modular-subscriptions::fms.tenant_subscription.you_have_reached_the_limit_of_this_module'),
            sprintf(
                '%s: %d/%d (%d%%)',
                $label,
                $usage,
                $limit,
                $percentage
            ),
            __('filament-modular-subscriptions::fms.tenant_subscription.upgrade_now'),
            'plans'
        );
    }

    protected function createAlert(string $type, string $title, string $body, string $actionLabel, string $param = 'subscription'): array
    {
        return [
            'type' => $type,
            'title' => $title,
            'body' => $body,
            'action' => [
                'label' => $actionLabel,
                'url' => TenantSubscription::getUrl(['tab' => $param]),
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

    protected function getSubscriptionAlerts(): array
    {
        $subscription = $this->tenant?->subscription;

        if ($subscription && $subscription->plan && $subscription->plan->isTrialPlan()) {
            if ($subscription->ends_at?->diffInDays(now()) <= 5) {
                $alerts[] = $this->createTrialEndingSoonAlert($subscription);
            }

            if ($subscription->status === SubscriptionStatus::EXPIRED) {
                $alerts[] = $this->createTrialExpiredAlert();
            }
        }

        return $alerts;
    }

    protected function createTrialEndingSoonAlert($subscription): array
    {
        return $this->createAlert(
            'warning',
            __('filament-modular-subscriptions::fms.tenant_subscription.trial_ending_soon'),
            __('filament-modular-subscriptions::fms.tenant_subscription.trial_ending_soon_message', [
                'days' => $subscription->ends_at->diffInDays(now())
            ]),
            __('filament-modular-subscriptions::fms.tenant_subscription.upgrade_now')
        );
    }

    protected function createTrialExpiredAlert(): array
    {
        return $this->createAlert(
            'danger',
            __('filament-modular-subscriptions::fms.tenant_subscription.trial_expired'),
            __('filament-modular-subscriptions::fms.tenant_subscription.trial_expired_message'),
            __('filament-modular-subscriptions::fms.tenant_subscription.choose_plan')
        );
    }
}
