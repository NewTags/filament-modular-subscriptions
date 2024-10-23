<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create(config('filament-modular-subscriptions.tables.plan_module'), function (Blueprint $table) {
            $table->id();
            $table->foreignId('plan_id')->constrained(config('filament-modular-subscriptions.tables.plan'))->onDelete('cascade');
            $table->foreignId('module_id')->constrained(config('filament-modular-subscriptions.tables.module'))->onDelete('cascade');
            $table->integer('limit')->nullable(); // Null means unlimited
            $table->decimal('price', 10, 2)->nullable();
            $table->json('settings')->nullable();
            $table->timestamps();

            // Ensure each module is only associated once with each plan
            $table->unique(['plan_id', 'module_id']);
        });
    }

    public function down()
    {
        Schema::dropIfExists(config('filament-modular-subscriptions.tables.plan_module'));
    }
};
