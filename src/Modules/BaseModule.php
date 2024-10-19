<?php

namespace HoceineEl\LaravelModularSubscriptions\Modules;

use HoceineEl\LaravelModularSubscriptions\Models\Subscription;

abstract class BaseModule
{
    abstract public function getName(): string;
    abstract public function getLabelKey(): string;
    abstract public function calculateUsage(Subscription $subscription): int;
    abstract public function getPricing(Subscription $subscription): float;
    abstract public function canUse(Subscription $subscription): bool;

    public function getLabel(): string
    {
        return __($this->getLabelKey());
    }
}
