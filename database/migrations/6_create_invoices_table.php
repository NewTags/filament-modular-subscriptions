<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create(config('filament-modular-subscriptions.tables.invoice'), function (Blueprint $table) {
            $table->id();
            $table->foreignId('subscription_id')->constrained(config('filament-modular-subscriptions.tables.subscription'))->cascadeOnDelete();
            $table->foreignId('tenant_id')->constrained(config('filament-modular-subscriptions.tenant_table', 'users'))->cascadeOnDelete();
            $table->decimal('amount', 10, 2);
            $table->decimal('tax', 10, 2);
            $table->string('status');
            $table->decimal('subtotal', 10, 2);
            $table->date('due_date');
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('invoices');
    }
};
