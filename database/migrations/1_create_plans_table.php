<?php

use HoceineEl\FilamentModularSubscriptions\Enums\Interval;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->json('name');
            $table->string('slug')->unique();
            $table->json('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->decimal('price', 10, 2)->default('0.00');
            $table->string('currency', 3)->default('USD');
            $table->unsignedSmallInteger('trial_period')->default(0);
            $table->string('trial_interval')->default(Interval::DAY->value);
            $table->unsignedSmallInteger('invoice_period')->default(0);
            $table->string('invoice_interval')->default(Interval::MONTH->value);
            $table->unsignedSmallInteger('grace_period')->default(0);
            $table->string('grace_interval')->default(Interval::DAY->value);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->softDeletes();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('plans');
    }
};
