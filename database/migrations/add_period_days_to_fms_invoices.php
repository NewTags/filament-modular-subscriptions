<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $invoices = config('filament-modular-subscriptions.tables.invoice');

        if (! Schema::hasColumn($invoices, 'period_days')) {
            Schema::table($invoices, function (Blueprint $table) {
                $table->unsignedInteger('period_days')->default(0)->after('due_date');
            });
        }
    }

    public function down(): void
    {
        $invoices = config('filament-modular-subscriptions.tables.invoice');

        if (Schema::hasColumn($invoices, 'period_days')) {
            Schema::table($invoices, fn (Blueprint $table) => $table->dropColumn('period_days'));
        }
    }
};
