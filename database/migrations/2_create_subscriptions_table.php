<?php

use HoceineEl\FilamentModularSubscriptions\Enums\SubscriptionStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create(config('filament-modular-subscriptions.tables.subscription'), function (Blueprint $table) {
            $table->id();
            $table->foreignId('plan_id')->constrained(config('filament-modular-subscriptions.tables.plan'))->onDelete('cascade');
            $table->morphs('subscribable');
            $table->timestamp('starts_at');
            $table->timestamp('ends_at')->nullable();
            $table->timestamp('trial_ends_at')->nullable();
            $table->string('status')->default(SubscriptionStatus::ACTIVE->value);
            $table->json('metadata')->nullable();
            $table->boolean('has_used_trial')->default(false);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down()
    {
        Schema::dropIfExists(config('filament-modular-subscriptions.tables.subscription'));
    }
};
