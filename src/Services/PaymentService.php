<?php

namespace HoceineEl\FilamentModularSubscriptions\Services;

use HoceineEl\FilamentModularSubscriptions\Enums\PaymentStatus;
use HoceineEl\FilamentModularSubscriptions\Models\Invoice;
use HoceineEl\FilamentModularSubscriptions\Models\Payment;

class PaymentService
{
    public function processPayment(Invoice $invoice)
    {
        // @todo : Implement  payment gateway charge logic here
        $paymentSuccessful = $this->chargeCustomer($invoice);

        if ($paymentSuccessful) {
            $this->createPaymentRecord($invoice, true);
            return (object) ['success' => true];
        } else {
            $this->createPaymentRecord($invoice, false);
            return (object) ['success' => false];
        }
    }

    private function chargeCustomer(Invoice $invoice)
    {
        // @todo : Implement  payment gateway charge logic here
        return rand(0, 1) === 1; // Simulate 50% success rate
    }

    private function createPaymentRecord(Invoice $invoice, bool $successful)
    {
        Payment::create([
            'invoice_id' => $invoice->id,
            'amount' => $invoice->amount,
            'payment_method' => 'credit_card', // @todo : Update this based on payment methods
            'transaction_id' => 'txn_' . uniqid(),
            'status' => $successful ? PaymentStatus::PAID : PaymentStatus::UNPAID,
        ]);
    }
}
