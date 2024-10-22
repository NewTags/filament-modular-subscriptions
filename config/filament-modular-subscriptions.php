<?php

return [
    'modules' => [
        // List all available module classes here
    ],
    'models' => [
        'plan' => HoceineEl\FilamentModularSubscriptions\Models\Plan::class,
        'subscription' => HoceineEl\FilamentModularSubscriptions\Models\Subscription::class,
        'module' => HoceineEl\FilamentModularSubscriptions\Models\Module::class,
        'usage' => HoceineEl\FilamentModularSubscriptions\Models\ModuleUsage::class,
        'plan_module' => HoceineEl\FilamentModularSubscriptions\Models\PlanModule::class,
        'invoice' => HoceineEl\FilamentModularSubscriptions\Models\Invoice::class,
        'invoice_item' => HoceineEl\FilamentModularSubscriptions\Models\InvoiceItem::class,
        'payment' => HoceineEl\FilamentModularSubscriptions\Models\Payment::class,
    ],
    // Tenant model and attribute to be used for the subscription relationship
    // 'tenant_model' => App\Models\User::class,
    // 'tenant_attribute' => 'name',
    'resources' => [
        'plan' => HoceineEl\FilamentModularSubscriptions\Resources\PlanResource::class,
        'subscription' => HoceineEl\FilamentModularSubscriptions\Resources\SubscriptionResource::class,
        'module' => HoceineEl\FilamentModularSubscriptions\Resources\ModuleResource::class,
        'usage' => HoceineEl\FilamentModularSubscriptions\Resources\ModuleUsageResource::class,
        'payment' => HoceineEl\FilamentModularSubscriptions\Resources\PaymentResource::class,
        'invoice' => HoceineEl\FilamentModularSubscriptions\Resources\InvoiceResource::class,
    ],
    'tables' => [
        'plan' => 'fms_plans',
        'subscription' => 'fms_subscriptions',
        'module' => 'fms_modules',
        'usage' => 'fms_module_usages',
        'plan_module' => 'fms_plan_modules',
        'invoice' => 'fms_invoices',
        'invoice_item' => 'fms_invoice_items',
        'payment' => 'fms_payments',

    ],
    'invoice_due_date_days' => 7,
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
    'main_currency' => 'USD',
    'translatable' => true,
    'locales' => [
        'en' => 'English',
        'ar' => 'Arabic',
    ],
    'payment_methods' => [
        'paypal' => [
            'enabled' => env('PAYPAL_ENABLED', false),
            'client_id' => env('PAYPAL_CLIENT_ID'),
            'secret' => env('PAYPAL_SECRET'),
            'mode' => env('PAYPAL_MODE', 'sandbox'), // or 'live'
        ],
        'stripe' => [
            'enabled' => env('STRIPE_ENABLED', false),
            'key' => env('STRIPE_KEY'),
            'secret' => env('STRIPE_SECRET'),
        ],
        // Add other payment method configurations here
    ],
];
