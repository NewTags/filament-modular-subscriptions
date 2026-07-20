<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $subscriptions = config('filament-modular-subscriptions.tables.subscription');
        $invoices = config('filament-modular-subscriptions.tables.invoice');

        if (! Schema::hasColumn($subscriptions, 'bonus_days')) {
            Schema::table($subscriptions, function (Blueprint $table) {
                $table->unsignedInteger('bonus_days')->default(0)->after('ends_at');
            });
        }

        if (! Schema::hasColumn($invoices, 'bonus_days')) {
            Schema::table($invoices, function (Blueprint $table) {
                $table->unsignedInteger('bonus_days')->default(0)->after('due_date');
            });
        }
    }

    public function down(): void
    {
        $subscriptions = config('filament-modular-subscriptions.tables.subscription');
        $invoices = config('filament-modular-subscriptions.tables.invoice');

        if (Schema::hasColumn($subscriptions, 'bonus_days')) {
            Schema::table($subscriptions, fn (Blueprint $table) => $table->dropColumn('bonus_days'));
        }

        if (Schema::hasColumn($invoices, 'bonus_days')) {
            Schema::table($invoices, fn (Blueprint $table) => $table->dropColumn('bonus_days'));
        }
    }
};
