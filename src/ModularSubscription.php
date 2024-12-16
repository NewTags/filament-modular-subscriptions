<?php

namespace NewTags\FilamentModularSubscriptions;

use Filament\Notifications\Notification;
use Illuminate\Support\Collection;

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

    public static function calculateUsageForAllModules(): void
    {
        $subscriptionModel = config('filament-modular-subscriptions.models.subscription');

        $subscriptionModel::with('subscriber')->get()->each(function ($subscription) {
            $subscription->subscriber->calculateUsage();
        });

        Notification::make()
            ->title(__('filament-modular-subscriptions::fms.resources.module_usage.actions.calculate_usage_success'))
            ->success()
            ->send();
    }
}
