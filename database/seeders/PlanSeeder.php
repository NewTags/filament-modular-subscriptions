<?php

namespace Database\Seeders;

use HoceineEl\FilamentModularSubscriptions\Enums\Interval;
use Illuminate\Database\Seeder;

class PlanSeeder extends Seeder
{
    public function run()
    {
        $planModel = config('filament-modular-subscriptions.models.plan');
        $moduleModel = config('filament-modular-subscriptions.models.module');

        $plans = [
            [
                'name' => ['en' => 'Basic Plan', 'ar' => 'الخطة الأساسية'],
                'slug' => 'basic-plan',
                'description' => ['en' => 'Basic features for small businesses', 'ar' => 'ميزات أساسية للشركات الصغيرة'],
                'is_active' => true,
                'price' => 9.99,
                'currency' => config('filament-modular-subscriptions.main_currency'),
                'trial_period' => 0,
                'trial_interval' => Interval::DAY,
                'invoice_period' => 1,
                'invoice_interval' => Interval::MONTH,
                'grace_period' => 0,
                'grace_interval' => Interval::DAY,
                'modules' => [
                    ['limit' => 100, 'price' => 0.99],
                    ['limit' => 50, 'price' => 1.99],
                ],
            ],
            [
                'name' => ['en' => 'Pay As You Go', 'ar' => 'إدفع حسب إستخدامك'],
                'slug' => 'pay-as-you-go',
                'is_pay_as_you_go' => true,
                'description' => ['en' => 'Pay as you go features', 'ar' => 'إدفع حسب إستخدامك'],
                'is_active' => true,
                'price' => 0,
                'currency' => config('filament-modular-subscriptions.main_currency'),
                'trial_period' => 14,
                'trial_interval' => Interval::DAY,
                'invoice_period' => 1,
                'invoice_interval' => Interval::MONTH,
                'grace_period' => 0,
                'grace_interval' => Interval::DAY,
                'modules' => [
                    ['limit' => null, 'price' => 1.99],
                    ['limit' => null, 'price' => 2.99],
                ],
            ],
        ];

        foreach ($plans as $planData) {
            $modules = $planData['modules'];
            unset($planData['modules']);

            $plan = $planModel::create($planData);

            $allModules = $moduleModel::all();

            foreach ($modules as $index => $moduleData) {
                if (isset($allModules[$index])) {
                    $plan->modules()->attach($allModules[$index]->id, $moduleData);
                }
            }
        }
    }
}
