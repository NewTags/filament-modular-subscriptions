<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table(config('filament-modular-subscriptions.tables.plan'), function (Blueprint $table) {
            $table->boolean('is_pay_as_you_go')->default(false)->after('is_active');
        });
    }

    public function down()
    {
        Schema::table(config('filament-modular-subscriptions.tables.plan'), function (Blueprint $table) {
            $table->dropColumn('is_pay_as_you_go');
        });
    }
};
