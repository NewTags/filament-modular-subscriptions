<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create(config('filament-modular-subscriptions.tables.usage'), function (Blueprint $table) {
            $table->id();
            $table->foreignId('subscription_id')->constrained(config('filament-modular-subscriptions.tables.subscription'))->onDelete('cascade');
            $table->foreignId('module_id')->constrained(config('filament-modular-subscriptions.tables.module'))->onDelete('cascade');
            $table->integer('usage');
            $table->decimal('pricing', 10, 2)->default(0);
            $table->timestamp('calculated_at');
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists(config('filament-modular-subscriptions.tables.usage'));
    }
};
