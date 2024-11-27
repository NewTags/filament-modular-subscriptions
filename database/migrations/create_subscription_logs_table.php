<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create(config('filament-modular-subscriptions.tables.subscription_log'), function (Blueprint $table) {
            $table->id();
            $table->foreignId('subscription_id')->constrained(config('filament-modular-subscriptions.tables.subscription'))->cascadeOnDelete();
            $table->string('event');
            $table->text('description');
            $table->string('old_status')->nullable();
            $table->string('new_status')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists(config('filament-modular-subscriptions.tables.subscription_log'));
    }
}; 