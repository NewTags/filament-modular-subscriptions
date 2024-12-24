<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        // Plans table indexes
        Schema::table(config('filament-modular-subscriptions.tables.plan'), function (Blueprint $table) {
            $table->index('price');
            $table->index('currency');
            $table->index('is_active');
            $table->index('invoice_interval');
            $table->index('grace_interval');
            $table->index('invoice_period');
            $table->index('grace_period');
            $table->index('is_pay_as_you_go');
            $table->index('is_trial_plan');
        });

        // Subscriptions table indexes
        Schema::table(config('filament-modular-subscriptions.tables.subscription'), function (Blueprint $table) {
            $table->index('plan_id');
            $table->index('status');
            $table->index('starts_at');
            $table->index('ends_at');
            $table->index('trial_ends_at');
            $table->index('has_used_trial');
            $table->index(['subscribable_type', 'subscribable_id']);
        });

        // Modules table indexes
        Schema::table(config('filament-modular-subscriptions.tables.module'), function (Blueprint $table) {
            $table->index('is_active');
            $table->index('is_persistent');
            $table->index('class');
        });

        // Module usages table indexes
        Schema::table(config('filament-modular-subscriptions.tables.usage'), function (Blueprint $table) {
            $table->index('usage');
            $table->index(['subscription_id', 'module_id']);
        });

        // Plan modules table indexes
        Schema::table(config('filament-modular-subscriptions.tables.plan_module'), function (Blueprint $table) {
            $table->index(['plan_id', 'module_id']);
            $table->index('limit');
            $table->index('price');
        });

        // Invoices table indexes
        Schema::table(config('filament-modular-subscriptions.tables.invoice'), function (Blueprint $table) {
            $table->index('status');
            $table->index('due_date');
            $table->index('paid_at');
            $table->index('tenant_id');
            $table->index(['subscription_id', 'status']);
        });

        // Invoice items table indexes
        Schema::table(config('filament-modular-subscriptions.tables.invoice_item'), function (Blueprint $table) {
            $table->index(['invoice_id', 'total']);
        });

        // Payments table indexes
        Schema::table(config('filament-modular-subscriptions.tables.payment'), function (Blueprint $table) {
            $table->index('status');
            $table->index('payment_method');
            $table->index(['invoice_id', 'status']);
        });
    }

    public function down()
    {
        // Plans table
        Schema::table(config('filament-modular-subscriptions.tables.plan'), function (Blueprint $table) {
            $table->dropIndex(['price']);
            $table->dropIndex(['currency']);
            $table->dropIndex(['is_active']);
            $table->dropIndex(['invoice_interval']);
            $table->dropIndex(['grace_interval']);
            $table->dropIndex(['invoice_period']);
            $table->dropIndex(['grace_period']);
            $table->dropIndex(['is_pay_as_you_go']);
            $table->dropIndex(['is_trial_plan']);
        });

        // Subscriptions table
        Schema::table(config('filament-modular-subscriptions.tables.subscription'), function (Blueprint $table) {
            $table->dropIndex(['plan_id']);
            $table->dropIndex(['status']);
            $table->dropIndex(['starts_at']);
            $table->dropIndex(['ends_at']);
            $table->dropIndex(['trial_ends_at']);
            $table->dropIndex(['has_used_trial']);
            $table->dropIndex(['subscribable_type', 'subscribable_id']);
        });

        // Modules table
        Schema::table(config('filament-modular-subscriptions.tables.module'), function (Blueprint $table) {
            $table->dropIndex(['is_active']);
            $table->dropIndex(['is_persistent']);
            $table->dropIndex(['class']);
        });

        // Module usages table
        Schema::table(config('filament-modular-subscriptions.tables.usage'), function (Blueprint $table) {
            $table->dropIndex(['usage']);
            $table->dropIndex(['subscription_id', 'module_id']);
        });

        // Plan modules table
        Schema::table(config('filament-modular-subscriptions.tables.plan_module'), function (Blueprint $table) {
            $table->dropIndex(['plan_id', 'module_id']);
            $table->dropIndex(['limit']);
            $table->dropIndex(['price']);
        });

        // Invoices table
        Schema::table(config('filament-modular-subscriptions.tables.invoice'), function (Blueprint $table) {
            $table->dropIndex(['status']);
            $table->dropIndex(['due_date']);
            $table->dropIndex(['paid_at']);
            $table->dropIndex(['tenant_id']);
            $table->dropIndex(['subscription_id', 'status']);
        });

        // Invoice items table
        Schema::table(config('filament-modular-subscriptions.tables.invoice_item'), function (Blueprint $table) {
            $table->dropIndex(['invoice_id', 'total']);
        });

        // Payments table
        Schema::table(config('filament-modular-subscriptions.tables.payment'), function (Blueprint $table) {
            $table->dropIndex(['status']);
            $table->dropIndex(['payment_method']);
            $table->dropIndex(['invoice_id', 'status']);
        });
    }
};
