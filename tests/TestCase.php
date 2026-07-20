<?php

namespace NewTags\FilamentModularSubscriptions\Tests;

use BladeUI\Heroicons\BladeHeroiconsServiceProvider;
use BladeUI\Icons\BladeIconsServiceProvider;
use Filament\Actions\ActionsServiceProvider;
use Filament\FilamentServiceProvider;
use Filament\Forms\FormsServiceProvider;
use Filament\Infolists\InfolistsServiceProvider;
use Filament\Notifications\NotificationsServiceProvider;
use Filament\Support\SupportServiceProvider;
use Filament\Tables\TablesServiceProvider;
use Filament\Widgets\WidgetsServiceProvider;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Livewire\LivewireServiceProvider;
use NewTags\FilamentModularSubscriptions\ModularSubscriptionsServiceProvider;
use NewTags\FilamentModularSubscriptions\Tests\Fixtures\TestTenant;
use NewTags\FilamentModularSubscriptions\Tests\Fixtures\User;
use Orchestra\Testbench\TestCase as Orchestra;
use RyanChandler\BladeCaptureDirective\BladeCaptureDirectiveServiceProvider;

class TestCase extends Orchestra
{
    private const PACKAGE_MIGRATIONS = [
        '1_create_plans_table',
        '2_create_subscriptions_table',
        '3_create_modules_table',
        '4_create_module_usages_table',
        '5_create_plan_modules_table',
        '6_create_invoices_table',
        '7_create_invoice_items_table',
        '8_create_payments_table',
        '9_create_payment_checkouts_table',
        'create_subscription_logs_table',
    ];

    protected function getPackageProviders($app)
    {
        return [
            ActionsServiceProvider::class,
            BladeCaptureDirectiveServiceProvider::class,
            BladeHeroiconsServiceProvider::class,
            BladeIconsServiceProvider::class,
            FilamentServiceProvider::class,
            FormsServiceProvider::class,
            InfolistsServiceProvider::class,
            LivewireServiceProvider::class,
            NotificationsServiceProvider::class,
            SupportServiceProvider::class,
            TablesServiceProvider::class,
            WidgetsServiceProvider::class,
            ModularSubscriptionsServiceProvider::class,
        ];
    }

    public function getEnvironmentSetUp($app)
    {
        config()->set('app.key', 'base64:' . base64_encode('a-32-character-string-for-tests!'));
        config()->set('database.default', 'testing');

        config()->set('auth.providers.users.model', User::class);

        config()->set('filament-modular-subscriptions.tenant_model', TestTenant::class);
        config()->set('filament-modular-subscriptions.tenant_attribute', 'name');
        config()->set('filament-modular-subscriptions.user_model', User::class);
        config()->set('filament-modular-subscriptions.main_currency', 'SAR');
        config()->set('filament-modular-subscriptions.currency_code', 'SAR');
        config()->set('filament-modular-subscriptions.payment_enabled', true);
        config()->set('filament-modular-subscriptions.online_payment_enabled', true);
        config()->set('filament-modular-subscriptions.payment_methods.tap', [
            'enabled' => true,
            'mode' => 'test',
            'merchant_id' => 'merchant_test_123',
            'test_secret_key' => 'sk_test_dummysecret',
            'test_public_key' => 'pk_test_dummypublic',
            'live_secret_key' => 'sk_live_dummysecret',
            'live_public_key' => 'pk_live_dummypublic',
        ]);
        config()->set('filament-modular-subscriptions.tenant_fields', [
            'name' => 'name',
            'address' => 'address',
            'vat_number' => 'vat_number',
            'email' => 'email',
            'phone' => null,
        ]);
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadLaravelMigrations();

        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });

        Schema::create('test_tenants', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('address')->nullable();
            $table->string('vat_number')->nullable();
            $table->timestamps();
        });

        Schema::create('test_tenant_users', function (Blueprint $table) {
            $table->id();
            $table->foreignId('test_tenant_id');
            $table->foreignId('user_id');
        });

        foreach (self::PACKAGE_MIGRATIONS as $migration) {
            (include __DIR__ . "/../database/migrations/{$migration}.php")->up();
        }
    }
}
