<?php

namespace HoceineEl\FilamentModularSubscriptions;

use Filament\Contracts\Plugin;
use Filament\Facades\Filament;
use Filament\Navigation\MenuItem;
use Filament\Panel;
use HoceineEl\FilamentModularSubscriptions\Resources\ModuleResource;
use HoceineEl\FilamentModularSubscriptions\Resources\ModuleUsageResource;
use HoceineEl\FilamentModularSubscriptions\Resources\PlanResource;
use HoceineEl\FilamentModularSubscriptions\Resources\SubscriptionResource;
use Outerweb\FilamentTranslatableFields\Filament\Plugins\FilamentTranslatableFieldsPlugin;

class ModularSubscriptionsPlugin implements Plugin
{
    protected bool $hasSubscriptionStats = true;


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
                ModuleUsageResource::class,
            ]);



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
}
