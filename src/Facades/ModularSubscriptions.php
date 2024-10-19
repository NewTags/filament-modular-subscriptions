<?php

namespace HoceineEl\LaravelModularSubscriptions\Facades;

use Illuminate\Support\Facades\Facade;
use HoceineEl\LaravelModularSubscriptions\ModularSubscriptionManager;
use Illuminate\Support\Collection;

/**
 * @method static void registerModule(string $moduleClass)
 * @method static Collection getRegisteredModules()
 * @method static Collection getActiveModules()
 *
 * @see \HoceineEl\LaravelModularSubscriptions\ModularSubscriptionManager
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
