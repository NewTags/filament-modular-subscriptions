<?php

namespace Database\Seeders;

use NewTags\FilamentModularSubscriptions\Enums\SubscriptionStatus;
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
            $startDate = now();

            // Calculate end date based on plan's invoice period and interval
            $endDate = $startDate->copy()->add(
                $plan->invoice_interval->value,
                $plan->invoice_period
            );

            // Calculate trial end date if trial period exists
            $trialEndDate = $plan->trial_period > 0
                ? $startDate->copy()->add(
                    $plan->trial_interval->value,
                    $plan->trial_period
                )
                : null;

            $subscriptionModel::create([
                'plan_id' => $plan->id,
                'subscribable_id' => $tenant->id,
                'subscribable_type' => get_class($tenant),
                'starts_at' => $startDate,
                'ends_at' => $endDate,
                'trial_ends_at' => $trialEndDate,
                'status' => SubscriptionStatus::ACTIVE,
            ]);
        }
    }
}
