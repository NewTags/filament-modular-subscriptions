<?php

return [
    'modules' => [
        // List all available module classes here
        // Example:
        // 'App\Fms\Modules\SubscriberModule::class',
    ],
    'models' => [
        'plan' => NewTags\FilamentModularSubscriptions\Models\Plan::class,
        'subscription' => NewTags\FilamentModularSubscriptions\Models\Subscription::class,
        'module' => NewTags\FilamentModularSubscriptions\Models\Module::class,
        'usage' => NewTags\FilamentModularSubscriptions\Models\ModuleUsage::class,
        'plan_module' => NewTags\FilamentModularSubscriptions\Models\PlanModule::class,
        'invoice' => NewTags\FilamentModularSubscriptions\Models\Invoice::class,
        'invoice_item' => NewTags\FilamentModularSubscriptions\Models\InvoiceItem::class,
        'payment' => NewTags\FilamentModularSubscriptions\Models\Payment::class,
        'subscription_log' => NewTags\FilamentModularSubscriptions\Models\SubscriptionLog::class,
    ],
    // Tenant model and attribute to be used for the subscription relationship
    // 'tenant_model' => App\Models\User::class,
    // 'tenant_attribute' => 'name',
    // user model
    // 'user_model' => App\Models\User::class,
    'resources' => [
        'plan' => NewTags\FilamentModularSubscriptions\Resources\PlanResource::class,
        'subscription' => NewTags\FilamentModularSubscriptions\Resources\SubscriptionResource::class,
        'module' => NewTags\FilamentModularSubscriptions\Resources\ModuleResource::class,
        'usage' => NewTags\FilamentModularSubscriptions\Resources\ModuleUsageResource::class,
        'payment' => NewTags\FilamentModularSubscriptions\Resources\PaymentResource::class,
        'invoice' => NewTags\FilamentModularSubscriptions\Resources\InvoiceResource::class,
        'log' => NewTags\FilamentModularSubscriptions\Resources\SubscriptionLogResource::class,
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
        'subscription_log' => 'fms_subscription_logs',
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
    'company_bank_account' => 'XXXXXXXXXXXXXXXXXXX',
    'company_bank_name' => 'XXXXXXXXXXXXXXXXXXX',
    'company_bank_iban' => 'XXXXXXXXXXXXXXXXXXX',
    'company_bank_swift' => 'XXXXXXXXXXXXXXXXXXX',
    'tax_percentage' => 15,
    'tenant_fields' => [
        'name' => 'name', // default field or custom accessor
        'address' => 'address', // could be 'info.address' or 'customerInfo.address'
        'vat_number' => 'tax_number', // could be 'vat_number' or 'vat_id' or 'info.vat'
        'email' => 'company_email', // could be 'contact_email' or 'info.email'
    ],
    'tenant_data_resolver' => null, // Can be set to a callable
    'invoice_generation_grace_period' => 0,
];
