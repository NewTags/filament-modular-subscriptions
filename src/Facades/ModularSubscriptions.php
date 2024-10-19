<?php

namespace HoceineEl\FilamentModularSubscriptions\Facades;

use Illuminate\Support\Facades\Facade;
use HoceineEl\FilamentModularSubscriptions\ModularSubscriptionManager;
use Illuminate\Support\Collection;

/**
 * @method static void registerModule(string $moduleClass)
 * @method static Collection getRegisteredModules()
 * @method static Collection getActiveModules()
 *
 * @see \HoceineEl\FilamentModularSubscriptions\ModularSubscriptionManager
 */
class ModularSubscriptions extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return 'modular-subscriptions';
    }
}
