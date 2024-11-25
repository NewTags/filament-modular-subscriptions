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
    'sends_invoice_email' => false,
    'payment_enabled' => false,
    'payment_methods' => [
        'paypal' => [
            'enabled' => env('PAYPAL_ENABLED', false),
            'client_id' => env('PAYPAL_CLIENT_ID'),
            'secret' => env('PAYPAL_SECRET'),
            'mode' => env('PAYPAL_MODE', 'sandbox'),
        ],
        'stripe' => [
            'enabled' => env('STRIPE_ENABLED', false),
            'key' => env('STRIPE_KEY'),
            'secret' => env('STRIPE_SECRET'),
        ],
        // Add other payment method configurations here,
    ],
    'font_path' => resource_path('fonts/Cairo'),
    'company_name' => 'إسم الشركة',
    'tax_number' => 'XXXXXXXXXXXXXXXXXXX',
    'company_address' => 'العنوان',
    'company_email' => 'info@company.com',
    'company_logo' => '/images/company-logo.png',
    'tax_percentage' => 15,
    'tenant_fields' => [
        'name' => 'name', // default field or custom accessor
        'address' => 'address', // could be 'info.address' or 'customerInfo.address'
        'vat_number' => 'tax_number', // could be 'vat_number' or 'vat_id' or 'info.vat'
        'email' => 'company_email', // could be 'contact_email' or 'info.email'
    ],
    'tenant_data_resolver' => null, // Can be set to a callable
];
