# Filament Modular Subscriptions

A powerful and flexible subscription management system for Laravel Filament applications. This package provides a complete solution for managing subscriptions with modular features, usage tracking, and automatic invoice generation.

[![Latest Version on Packagist](https://img.shields.io/packagist/v/hoceineel/filament-modular-subscriptions.svg?style=flat-square)](https://packagist.org/packages/hoceineel/filament-modular-subscriptions)
[![Total Downloads](https://img.shields.io/packagist/dt/hoceineel/filament-modular-subscriptions.svg?style=flat-square)](https://packagist.org/packages/hoceineel/filament-modular-subscriptions)

## Features

- 🔥 Fully integrated with Filament Admin Panel
- 📦 Modular subscription features
- 💰 Pay-as-you-go and fixed pricing support
- 📊 Usage tracking and limits
- 🧾 Automatic invoice generation
- 🌍 Multi-language support (including RTL)
- ⏱️ Trial periods and grace periods
- 🔄 Subscription switching and renewals
- 📄 PDF invoice generation
- 🔍 Detailed usage analytics
- 🎯 Custom module creation,Seeder,Resource,Tables,Migrations

## Requirements

- PHP 8.1 or higher
- Laravel 10.0 or higher
- Filament 3.0 or higher

## Installation

1. Install the package via composer:

```bash
composer require hoceineel/filament-modular-subscriptions
```

2. Publish and run the migrations:

```bash
php artisan vendor:publish --tag="filament-modular-subscriptions-migrations"
php artisan migrate
```

3. Publish the configuration file:

```bash
php artisan vendor:publish --tag="filament-modular-subscriptions-config"
```

4. Publish translations:

```bash
php artisan vendor:publish --tag="filament-modular-subscriptions-translations"
```

5. Publish views:

```bash
php artisan vendor:publish --tag="filament-modular-subscriptions-views"
```

6. Publish seeders:

```bash
php artisan vendor:publish --tag="filament-modular-subscriptions-seeders"
```

## Configuration

The published config file `config/filament-modular-subscriptions.php` allows you to customize various aspects of the package:

```php
return [
    'modules' => [
        \App\Modules\ApiCallsModule::class,
        \App\Modules\StorageModule::class,
    ],
    'models' => [
        'plan' => \NewTags\FilamentModularSubscriptions\Models\Plan::class,
        'subscription' => \NewTags\FilamentModularSubscriptions\Models\Subscription::class,
        // ... other models
    ],
    'resources' => [
        'plan' => \NewTags\FilamentModularSubscriptions\Resources\PlanResource::class,
        'subscription' => \NewTags\FilamentModularSubscriptions\Resources\SubscriptionResource::class,
        // ... other resources
    ],
    'tables' => [
        'plan' => 'fms_plans',
        'subscription' => 'fms_subscriptions',
        // ... other tables
    ],
    'tenant_model' => \App\Models\User::class,
    'tenant_attribute' => 'name',
    'main_currency' => 'USD',
];
```

## Basic Usage

### Registering the Plugin

```php
use NewTags\FilamentModularSubscriptions\ModularSubscriptionsPlugin;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->plugins([
                ModularSubscriptionsPlugin::make(),
            ]);
    }
}
```

### Custom Actions After Invoice Payment

You can define custom actions to execute after an invoice is paid by using the `afterInvoicePaid` method:

```php
use NewTags\FilamentModularSubscriptions\FmsPlugin;

// In your service provider
FmsPlugin::make()
    ->afterInvoicePaid(function ($invoice) {
        // Create expense record
        Expense::create([
            'amount' => $invoice->amount,
            'description' => "Subscription payment for {$invoice->subscription->subscribable->name}",
            'date' => now(),
        ]);
        
        // Send custom notifications
        // Update accounting records
        // Or any other custom logic
    });
```

### Creating a Module

```php
use NewTags\FilamentModularSubscriptions\Modules\BaseModule;
use NewTags\FilamentModularSubscriptions\Models\Subscription;

class ApiCallsModule extends BaseModule
{
    public function getName(): string
    {
        return 'API Calls';
    }

    public function getLabelKey(): string
    {
        return 'api_calls_module';
    }

    public function calculateUsage(Subscription $subscription): int
    {
        return FmsPlugin::getTenant()->moduleUsage(get_class($this));
    }

   public function getPricing(Subscription $subscription): float
    {
        return $this->calculateUsage($subscription) * $subscription->plan->modulePrice(get_class($this));
    }

    public function canUse(Subscription $subscription): bool
    {
        if ($subscription->plan->is_pay_as_you_go) {
            return true;
        }
        return $this->calculateUsage($subscription) < $subscription->plan->moduleLimit(get_class($this));
    }
}
```

Register your module in the config:

```php
'modules' => [
    \App\Modules\ApiCallsModule::class,
],
```

### Creating a Plan

```php
use NewTags\FilamentModularSubscriptions\Models\Plan;
use NewTags\FilamentModularSubscriptions\Enums\Interval;

$plan = Plan::create([
    'name' => ['en' => 'Pro Plan', 'ar' => 'الخطة الاحترافية'],
    'slug' => 'pro-plan',
    'description' => ['en' => 'Our premium offering', 'ar' => 'عرضنا المتميز'],
    'is_active' => true,
    'price' => 99.99,
    'currency' => 'USD',
    'trial_period' => 14,
    'trial_interval' => Interval::DAY,
    'invoice_period' => 1,
    'invoice_interval' => Interval::MONTH,
    'grace_period' => 3,
    'grace_interval' => Interval::DAY,
]);

// Attach modules to the plan
$apiCallsModule = \App\Modules\ApiCallsModule::first();
$plan->modules()->attach($apiCallsModule->id, [
    'limit' => 1000,
    'price' => 0.01
]);
```

### Creating a Subscription

```php
use NewTags\FilamentModularSubscriptions\Models\Subscription;
use NewTags\FilamentModularSubscriptions\Enums\SubscriptionStatus;

$user = User::find(1);
$plan = Plan::where('slug', 'pro-plan')->first();

$subscription = Subscription::create([
    'plan_id' => $plan->id,
    'subscribable_id' => $user->id,
    'subscribable_type' => get_class($user),
    'starts_at' => now(),
    'ends_at' => now()->addMonth(),
    'trial_ends_at' => now()->addDays(14),
    'status' => SubscriptionStatus::ACTIVE,
]);
```

### Checking Subscription Status

```php
$subscription = User::find(1)->subscription;

if ($subscription->onTrial()) {
    echo "This subscription is currently on trial.";
}

if ($subscription->hasExpiredTrial()) {
    echo "The trial period for this subscription has ended.";
}

if ($subscription->isActive()) {
    echo "This subscription is active.";
}

if ($subscription->isInactive()) {
    echo "This subscription is inactive.";
}

if ($subscription->isCancelled()) {
    echo "This subscription has been cancelled.";
}

if ($subscription->hasEnded()) {
    echo "This subscription has ended.";
}
```

### Working with Invoices

```php
use NewTags\FilamentModularSubscriptions\Services\InvoiceService;

// Generate an invoice
$invoiceService = app(InvoiceService::class);
$invoice = $invoiceService->generateInvoice($subscription);

// Access tenant's invoices
$user = User::find(1);
$invoices = $user->invoices;

foreach ($invoices as $invoice) {
    echo "Invoice #{$invoice->id}: {$invoice->amount} {$invoice->subscription->plan->currency}";
    echo "Status: {$invoice->status->getLabel()}";
    echo "Due Date: {$invoice->due_date->format('Y-m-d')}";
}
```


## Clear Cache when needed for tenant

```php
clear_fms_cache();
```

## Use Case: Online Academy

Here's a complete example of implementing a subscription system for an online academy.

### Step 1: Define Modules

```php
class CourseAccessModule extends BaseModule
{
    public function getName(): string
    {
        return 'Course Access';
    }

    public function getLabelKey(): string
    {
        return 'course_access_module';
    }

    public function calculateUsage(Subscription $subscription): int
    {
        return $subscription->subscribable->accessed_courses_count;
    }
}

class LiveSessionModule extends BaseModule
{
    public function getName(): string
    {
        return 'Live Sessions';
    }

    public function getLabelKey(): string
    {
        return 'live_session_module';
    }

    public function calculateUsage(Subscription $subscription): int
    {
        return $subscription->subscribable->attended_live_sessions_count;
    }
}
```

### Step 2: Create Plans

```php
$basicPlan = Plan::create([
    'name' => ['en' => 'Basic Plan', 'ar' => 'الخطة الأساسية'],
    'slug' => 'basic-plan',
    'description' => ['en' => 'Access to basic courses', 'ar' => 'الوصول إلى الدورات الأساسية'],
    'is_active' => true,
    'price' => 29.99,
    'currency' => 'USD',
    'trial_period' => 7,
    'trial_interval' => Interval::DAY,
    'invoice_period' => 1,
    'invoice_interval' => Interval::MONTH,
]);

$basicPlan->modules()->attach($courseAccessModule->id, [
    'limit' => 5,
    'price' => 9.99
]);

$proPlan = Plan::create([
    'name' => ['en' => 'Pro Plan', 'ar' => 'الخطة الاحترافية'],
    'slug' => 'pro-plan',
    'description' => ['en' => 'Unlimited course access with live sessions', 'ar' => 'وصول غير محدود للدورات مع جلسات مباشرة'],
    'is_active' => true,
    'price' => 99.99,
    'currency' => 'USD',
    'trial_period' => 14,
    'trial_interval' => Interval::DAY,
    'invoice_period' => 1,
    'invoice_interval' => Interval::MONTH,
]);

$proPlan->modules()->attach($courseAccessModule->id, [
    'limit' => null,
    'price' => 0
]);

$proPlan->modules()->attach($liveSessionModule->id, [
    'limit' => 4,
    'price' => 19.99
]);
```

### Step 3: Manage Subscriptions

```php
$student = Student::find(1);
$plan = Plan::where('slug', 'pro-plan')->first();

$subscription = Subscription::create([
    'plan_id' => $plan->id,
    'subscribable_id' => $student->id,
    'subscribable_type' => get_class($student),
    'starts_at' => now(),
    'ends_at' => now()->addMonth(),
    'trial_ends_at' => now()->addDays(14),
    'status' => SubscriptionStatus::ACTIVE,
]);
```

### Step 4: Check Module Access

```php 
$student = Student::find(1);
$subscription = $student->subscription;

$courseAccessModule = Module::where('name', 'Course Access')->first();
$liveSessionModule = Module::where('name', 'Live Sessions')->first();

if ($courseAccessModule->canUse($subscription)) {
    // Allow access to the course
} else {
    // Show upgrade options or restrict access
}

if ($liveSessionModule->canUse($subscription)) {
    // Allow attendance to live session
} else {
    // Show upgrade options or restrict access
}
```

### Step 5: Generate Invoices

```php
$invoiceService = app(InvoiceService::class);

foreach (Subscription::active()->get() as $subscription) {
    $invoice = $invoiceService->generateInvoice($subscription);
    // Send invoice to student
}
```


#todo add in readme desc

- customizing seeders

## Testing

```bash
composer test
```

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

## Security

If you discover any security-related issues, please review [our security policy](../../security/policy) for reporting procedures.

## Credits

- [Hoceine El](https://github.com/hoceineel)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.