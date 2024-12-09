<?php

namespace HoceineEl\FilamentModularSubscriptions;

use Closure;
use Filament\Contracts\Plugin;
use Filament\Navigation\MenuItem;
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
    private const SUBSCRIPTION_ENDING_DAYS_THRESHOLD = 7;
    private const TRIAL_ENDING_DAYS_THRESHOLD = 5;

    public bool $hasSubscriptionStats = true;
    public bool $onTenantPanel = false;
    public static ?Closure $getTenantUsing = null;
    public ?Model $tenant = null;
    public array $cachedAlerts = [];
    public ?string $navigationGroup = null;
    public ?string $tenantNavigationGroup = null;
    public ?string $subscriptionNavigationLabel = null;

    private array $alertTypes = [
        'no_subscription' => [
            'type' => 'warning',
            'title_key' => 'fms.tenant_subscription.no_active_subscription',
            'body_key' => 'fms.tenant_subscription.no_subscription_message',
            'action_key' => 'fms.tenant_subscription.select_plan'
        ],
        'expired' => [
            'type' => 'danger',
            'title_key' => 'fms.statuses.expired',
            'body_key' => 'fms.tenant_subscription.you_have_to_renew_your_subscription',
            'action_key' => 'fms.tenant_subscription.pay_invoice'
        ],
        'on_hold' => [
            'type' => 'warning',
            'title_key' => 'fms.tenant_subscription.subscription_on_hold',
            'body_key' => 'fms.tenant_subscription.subscription_on_hold_message',
            'action_key' => 'fms.tenant_subscription.pay_invoice'
        ],
        'pending_payment' => [
            'type' => 'warning',
            'title_key' => 'fms.tenant_subscription.subscription_pending_payment',
            'body_key' => 'fms.tenant_subscription.subscription_pending_payment_message',
            'action_key' => 'fms.tenant_subscription.pay_invoice'
        ],
        'ending_soon' => [
            'type' => 'warning',
            'title_key' => 'fms.tenant_subscription.subscription_ending_soon',
            'body_key' => 'fms.tenant_subscription.days_left',
            'action_key' => 'fms.tenant_subscription.select_plan'
        ],
        'trial_ending_soon' => [
            'type' => 'warning',
            'title_key' => 'fms.tenant_subscription.trial_ending_soon',
            'body_key' => 'fms.tenant_subscription.trial_ending_soon_message',
            'action_key' => 'fms.tenant_subscription.upgrade_now'
        ],
        'trial_expired' => [
            'type' => 'danger',
            'title_key' => 'fms.tenant_subscription.trial_expired',
            'body_key' => 'fms.tenant_subscription.trial_expired_message',
            'action_key' => 'fms.tenant_subscription.choose_plan'
        ]
    ];

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
        if (! $this->onTenantPanel || ! ($tenant = self::getTenant())) {
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
        return Cache::rememberForever($cacheKey, fn() => $this->generateAlerts($tenant));
    }

    protected function generateAlerts(Model $tenant): array
    {
        $subscription = $tenant->subscription;

        // Check for no subscription or cancelled status
        if (! $subscription || $subscription->status === SubscriptionStatus::CANCELLED) {
            return [$this->createAlertFromType('no_subscription')];
        }

        // Check for expired subscription first
        if ($this->isSubscriptionExpired($subscription)) {
            return [$this->createAlertFromType('expired')];
        }

        $alerts = [];

        // Check subscription status alerts
        $statusAlerts = [
            SubscriptionStatus::ON_HOLD => 'on_hold',
            SubscriptionStatus::PENDING_PAYMENT => 'pending_payment',
        ];

        if (isset($statusAlerts[$subscription->status])) {
            return [$this->createAlertFromType($statusAlerts[$subscription->status])];
        }

        // Handle trial plan alerts
        if ($subscription->plan->isTrialPlan()) {
            if ($subscription->status === SubscriptionStatus::EXPIRED) {
                return [$this->createAlertFromType('trial_expired')];
            }

            if ($subscription->ends_at && $subscription->ends_at->diffInDays(now()) <= self::TRIAL_ENDING_DAYS_THRESHOLD) {
                $alerts[] = $this->createAlertFromType('trial_ending_soon', [
                    'days' => $subscription->ends_at->diffInDays(now())
                ]);
            }
        }

        // Check if subscription is ending soon
        if ($this->isSubscriptionEndingSoon($subscription)) {
            $alerts[] = $this->createEndingSoonAlert($subscription);
        }

        // Add any module usage alerts
        return array_merge($alerts, $this->generateModuleUsageAlerts($subscription, $tenant));
    }

    protected function isSubscriptionExpired($subscription): bool
    {
        return $subscription->ends_at && $subscription->ends_at->isPast();
    }

    protected function isSubscriptionEndingSoon($subscription): bool
    {
        return $subscription->ends_at && $subscription->daysLeft() <= self::SUBSCRIPTION_ENDING_DAYS_THRESHOLD;
    }

    protected function generateModuleUsageAlerts($subscription, $tenant): array
    {
        $alerts = [];
        $moduleUsages = $subscription->moduleUsages()->with(['module.planModules' => function ($query) use ($subscription) {
            $query->where('plan_id', $subscription->plan_id);
        }])->get();

        foreach ($moduleUsages as $moduleUsage) {
            $module = $moduleUsage->module;
            if (! $tenant->canUseModule($module->class)) {
                $limit = $module->planModules->first()?->limit;
                $alerts[] = $this->createModuleLimitAlert($module, $moduleUsage, $limit);
            }
        }

        return $alerts;
    }

    protected function createAlertFromType(string $type, array $params = []): array
    {
        $alertConfig = $this->alertTypes[$type];
        return $this->createAlert(
            $alertConfig['type'],
            __('filament-modular-subscriptions::' . $alertConfig['title_key']),
            __('filament-modular-subscriptions::' . $alertConfig['body_key'], $params),
            __('filament-modular-subscriptions::' . $alertConfig['action_key'])
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

    protected function getSubscriptionAlerts(): array
    {
        $subscription = $this->tenant?->subscription;

        if ($subscription && $subscription->plan->isTrialPlan()) {
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
