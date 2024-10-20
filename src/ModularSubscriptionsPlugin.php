<?php

namespace HoceineEl\FilamentModularSubscriptions;

use Closure;
use Filament\Contracts\Plugin;
use Filament\Navigation\MenuItem;
use Filament\Panel;
use Outerweb\FilamentTranslatableFields\Filament\Plugins\FilamentTranslatableFieldsPlugin;

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

        if (!$this->onTenantPanel) {
            $panel
                ->plugin(FilamentTranslatableFieldsPlugin::make())
                ->resources(config('filament-modular-subscriptions.resources'));
        } else {
            $panel
                ->userMenuItems([
                    MenuItem::make()
                        ->label('test')
                        ->url('#'),
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
}
