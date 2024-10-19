<?php

return [
    'modules' => [
        // List all available module classes here
    ],
    'models' => [
        'plan' => HoceineEl\LaravelModularSubscriptions\Models\Plan::class,
        'subscription' => HoceineEl\LaravelModularSubscriptions\Models\Subscription::class,
        'module' => HoceineEl\LaravelModularSubscriptions\Models\Module::class,
        'usage' => HoceineEl\LaravelModularSubscriptions\Models\ModuleUsage::class,
    ],
    'resources' => [
        'plan' => HoceineEl\FilamentModularSubscriptions\Resources\PlanResource::class,
        'subscription' => HoceineEl\FilamentModularSubscriptions\Resources\SubscriptionResource::class,
        'module' => HoceineEl\FilamentModularSubscriptions\Resources\ModuleResource::class,
        'usage' => HoceineEl\FilamentModularSubscriptions\Resources\ModuleUsageResource::class,
    ],
    'currencies' => [
        'USD',
        'SAR',
        'EUR',
        'GBP',
        'MAD',
        'AED',
        'QAR',
        'KWD',
        'BHD',
        'OMR',
        'JOD',
        'LYD',
        'EGP',
        'SDG',
        'TND',
        'LBP',
        'SYP',
        'IQD',
        'KHR',
        'LAK',
        'MMK',
        'MNT',
    ],
    'translatable' => true,
    'locales' => [
        'en',
        'ar',
    ],
];
