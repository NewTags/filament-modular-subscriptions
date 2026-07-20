<?php

use NewTags\FilamentModularSubscriptions\Models\Invoice;
use NewTags\FilamentModularSubscriptions\Models\InvoiceItem;
use NewTags\FilamentModularSubscriptions\Models\Module;
use NewTags\FilamentModularSubscriptions\Models\ModuleUsage;
use NewTags\FilamentModularSubscriptions\Models\Payment;
use NewTags\FilamentModularSubscriptions\Models\PaymentCheckout;
use NewTags\FilamentModularSubscriptions\Models\Plan;
use NewTags\FilamentModularSubscriptions\Models\PlanModule;
use NewTags\FilamentModularSubscriptions\Models\Subscription;
use NewTags\FilamentModularSubscriptions\Models\SubscriptionLog;
use NewTags\FilamentModularSubscriptions\Resources\InvoiceResource;
use NewTags\FilamentModularSubscriptions\Resources\ModuleResource;
use NewTags\FilamentModularSubscriptions\Resources\ModuleUsageResource;
use NewTags\FilamentModularSubscriptions\Resources\PaymentResource;
use NewTags\FilamentModularSubscriptions\Resources\PlanResource;
use NewTags\FilamentModularSubscriptions\Resources\SubscriptionLogResource;
use NewTags\FilamentModularSubscriptions\Resources\SubscriptionResource;

return [
    'modules' => [
        // List all available module classes here
        // Example:
        // 'App\Fms\Modules\SubscriberModule::class',
    ],
    'models' => [
        'plan' => Plan::class,
        'subscription' => Subscription::class,
        'module' => Module::class,
        'usage' => ModuleUsage::class,
        'plan_module' => PlanModule::class,
        'invoice' => Invoice::class,
        'invoice_item' => InvoiceItem::class,
        'payment' => Payment::class,
        'payment_checkout' => PaymentCheckout::class,
        'subscription_log' => SubscriptionLog::class,
    ],
    // Tenant model and attribute to be used for the subscription relationship
    // 'tenant_model' => App\Models\User::class,
    // 'tenant_attribute' => 'name',
    // user model
    // 'user_model' => App\Models\User::class,
    'resources' => [
        'plan' => PlanResource::class,
        'subscription' => SubscriptionResource::class,
        'module' => ModuleResource::class,
        'usage' => ModuleUsageResource::class,
        'payment' => PaymentResource::class,
        'invoice' => InvoiceResource::class,
        'log' => SubscriptionLogResource::class,
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
        'payment_checkout' => 'fms_payment_checkouts',
        'subscription_log' => 'fms_subscription_logs',
        'setting' => 'fms_settings',
    ],

    'main_currency' => 'SAR',
    // ISO 4217 code used for payment gateways and webhook signatures. Keep it a real
    // currency code even when `main_currency` is overridden with a display symbol.
    'currency_code' => env('FMS_CURRENCY_CODE', 'SAR'),
    'translatable' => true,
    'locales' => [
        'en' => 'English',
        'ar' => 'Arabic',
    ],
    'sends_invoice_email' => false,
    'payment_enabled' => false,
    // Set true only once a real online gateway (Stripe/PayPal) is wired up; otherwise only bank transfer is offered.
    'online_payment_enabled' => false,
    // Payment receipts contain financial data; store them on a PRIVATE disk and serve them only through the panel.
    'receipts_disk' => 'local',
    'payment_methods' => [
        'tap' => [
            'enabled' => env('TAP_ENABLED', false),
            // 'test' uses the test key pair, 'live' the live pair. The Tap API base
            // URL is identical for both — the key prefix selects the environment.
            'mode' => env('TAP_MODE', 'test'),
            'merchant_id' => env('TAP_MERCHANT_ID'),
            'test_secret_key' => env('TAP_TEST_SECRET_KEY'),
            'test_public_key' => env('TAP_TEST_PUBLIC_KEY'),
            'live_secret_key' => env('TAP_LIVE_SECRET_KEY'),
            'live_public_key' => env('TAP_LIVE_PUBLIC_KEY'),
        ],
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

    'payments' => [
        // How long (days) a shared public payment link stays valid.
        'link_ttl_days' => 30,
        'http_timeout' => 15,
        'default_phone_country_code' => '966',
        // class-string of a ResolvesGatewayCredentials implementation. Leave null to
        // charge with the platform account configured in `payment_methods` above;
        // bind a custom resolver to charge per-tenant merchant accounts (B2C).
        'credentials_resolver' => null,
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
    'widgets' => [
        // enable module usage widget to show the usage of each module in  subscription
        'enable_module_usage' => true,
    ],
    'notifications' => [
        // enable past due invoice notification to notify the tenant when their invoice is past due
        'enable_past_due_invoice_notification' => false,
        // enable subscription near expiry notification to notify the tenant when their subscription is near expiry
        'enable_subscription_near_expiry_notification' => false,
        // number of days before subscription expiry to notify the tenant
        'subscription_near_expiry_days' => 5,
    ],
];
