<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create(config('filament-modular-subscriptions.tables.payment'), function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained(config('filament-modular-subscriptions.tables.invoice'))->cascadeOnDelete();
            $table->decimal('amount', 10, 2);
            $table->string('payment_method');
            $table->string('transaction_id');
            $table->string('status');
            $table->string('receipt_file')->nullable();
            $table->text('admin_notes')->nullable(); // Added for admin notes during review
            $table->timestamp('reviewed_at')->nullable(); // Added to track when payment was reviewed
            $table->foreignId('reviewed_by')->nullable()->constrained('users'); // Added to track who reviewed
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists(config('filament-modular-subscriptions.tables.payment'));
    }
};
