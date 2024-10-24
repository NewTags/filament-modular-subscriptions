<?php

namespace HoceineEl\FilamentModularSubscriptions\Database\Seeders;

use HoceineEl\FilamentModularSubscriptions\Enums\SubscriptionStatus;
use Illuminate\Database\Seeder;

class SubscriptionSeeder extends Seeder
{
    public function run()
    {
        $tenantModel = config('filament-modular-subscriptions.tenant_model');

        if (! $tenantModel) {
            $this->command->warn('Tenant model not set in config. Skipping subscription seeding.');

            return;
        }
        $planModel = config('filament-modular-subscriptions.models.plan');
        $subscriptionModel = config('filament-modular-subscriptions.models.subscription');
        $tenants = $tenantModel::all();
        $plans = $planModel::all();

        foreach ($tenants as $tenant) {
            $plan = $plans->random();

            $subscriptionModel::create([
                'plan_id' => $plan->id,
                'subscribable_id' => $tenant->id,
                'subscribable_type' => get_class($tenant),
                'starts_at' => now(),
                'ends_at' => now()->addDays($plan->period),
                'trial_ends_at' => now()->addDays($plan->period_trial),
                'status' => SubscriptionStatus::ACTIVE,
            ]);
        }
    }
}
