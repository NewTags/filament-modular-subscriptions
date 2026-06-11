<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create(config('filament-modular-subscriptions.tables.payment_checkout', 'fms_payment_checkouts'), function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('invoice_id')->constrained(config('filament-modular-subscriptions.tables.invoice'))->cascadeOnDelete();
            $table->string('gateway');
            $table->string('charge_id')->nullable();
            $table->decimal('amount', 10, 2);
            $table->string('currency', 3);
            $table->string('status');
            $table->text('checkout_url')->nullable();
            $table->text('return_url')->nullable();
            $table->string('source')->default('panel');
            $table->foreignId('payment_id')->nullable()->constrained(config('filament-modular-subscriptions.tables.payment'))->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->json('metadata')->nullable();
            $table->json('gateway_response')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->unique(['gateway', 'charge_id']);
            $table->index(['invoice_id', 'status']);
        });
    }

    public function down()
    {
        Schema::dropIfExists(config('filament-modular-subscriptions.tables.payment_checkout', 'fms_payment_checkouts'));
    }
};
