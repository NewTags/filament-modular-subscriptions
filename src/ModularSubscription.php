<?php

namespace HoceineEl\FilamentModularSubscriptions;

use Illuminate\Support\Collection;

/**
 * @method static Collection getRegisteredModules()
 * @method static Collection getActiveModules()
 *
 */
class ModularSubscription
{

    public static function getRegisteredModules(): Collection
    {
        $moduleModel = config('filament-modular-subscriptions.models.module');

        return $moduleModel::all();
    }

    public static function getActiveModules(): Collection
    {
        $moduleModel = config('filament-modular-subscriptions.models.module');

        return $moduleModel::active()->get();
    }

    public static function registerModule(string $moduleClass): void
    {
        $moduleModel = config('filament-modular-subscriptions.models.module');

        $moduleModel::registerModule($moduleClass);
    }
}
