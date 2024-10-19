<?php

namespace HoceineEl\FilamentModularSubscriptions;

use Filament\Contracts\Plugin;
use Filament\Facades\Filament;
use Filament\Navigation\MenuItem;
use Filament\Navigation\UserMenuItem;
use Filament\Panel;
use HoceineEl\FilamentModularSubscriptions\Resources\PlanResource;
use HoceineEl\FilamentModularSubscriptions\Resources\SubscriptionResource;
use HoceineEl\FilamentModularSubscriptions\Resources\ModuleResource;
use Outerweb\FilamentTranslatableFields\Filament\Plugins\FilamentTranslatableFieldsPlugin;

class ModularSubscriptionsPlugin implements Plugin
{
    protected bool $hasSubscriptionStats = true;

    protected  ?string $tenantPanelId = null;

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
        $panel
            ->plugin(FilamentTranslatableFieldsPlugin::make())
            ->resources([
                PlanResource::class,
                SubscriptionResource::class,
                ModuleResource::class,
            ]);

        if ($this->tenantPanelId) {
            Filament::getPanel($this->tenantPanelId)
                ->userMenuItems([
                    MenuItem::make()
                        ->label(__('Manage Your Subscription'))
                        ->icon('heroicon-o-credit-card')
                        ->url(fn(): string => "#"),
                ]);
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

    public function tenantPanelId(string $tenantPanelId): static
    {
        static::$tenantPanelId = $tenantPanelId;

        return $this;
    }

    // public function subscriptionStats(bool $condition = true): static
    // {
    //     $this->hasSubscriptionStats = $condition;

    //     return $this;
    // }

    // public function hasSubscriptionStats(): bool
    // {
    //     return $this->hasSubscriptionStats;
    // }

    public static function get(): static
    {
        return filament(app(static::class)->getId());
    }
}
