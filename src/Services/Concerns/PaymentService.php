<?php

namespace NewTags\FilamentModularSubscriptions\Services\Concerns;

use NewTags\FilamentModularSubscriptions\Enums\PaymentStatus;
use NewTags\FilamentModularSubscriptions\Models\Invoice;
use NewTags\FilamentModularSubscriptions\Models\Payment;

abstract class PaymentService
{
    abstract public function processPayment(Invoice $invoice): Payment;

    abstract public function getPaymentMethod(): string;

    protected function createPaymentRecord(Invoice $invoice, bool $successful): Payment
    {
        return Payment::create([
            'invoice_id' => $invoice->id,
            'amount' => $invoice->amount,
            'payment_method' => $this->getPaymentMethod(),
            'transaction_id' => $this->generateTransactionId(),
            'status' => $successful ? PaymentStatus::PAID : PaymentStatus::UNPAID,
        ]);
    }

    protected function generateTransactionId(): string
    {
        return 'txn_' . uniqid();
    }
}
