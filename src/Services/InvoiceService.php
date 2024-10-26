<?php

namespace HoceineEl\FilamentModularSubscriptions\Services;

use HoceineEl\FilamentModularSubscriptions\Enums\PaymentStatus;
use HoceineEl\FilamentModularSubscriptions\Models\Invoice;
use HoceineEl\FilamentModularSubscriptions\Models\Subscription;

class InvoiceService
{
    public function generateInvoice(Subscription $subscription): Invoice
    {
        $invoiceModel = config('filament-modular-subscriptions.models.invoice');
        $invoice = $invoiceModel::create([
            'subscription_id' => $subscription->id,
            'tenant_id' => $subscription->subscribable_id,
            'amount' => $this->calculateTotalAmount($subscription),
            'status' => PaymentStatus::UNPAID,
            'due_date' => now()->addDays(config('filament-modular-subscriptions.invoice_due_date_days')),
        ]);

        $this->createInvoiceItems($invoice, $subscription);

        return $invoice;
    }

    private function calculateTotalAmount(Subscription $subscription): float
    {
        $total = 0;
        if ($subscription->plan->is_pay_as_you_go) {
            foreach ($subscription->moduleUsages as $moduleUsage) {
                $total += $moduleUsage->pricing;
            }
        } else {
            $total = $subscription->plan->price;
        }

        return $total;
    }

    private function createInvoiceItems(Invoice $invoice, Subscription $subscription): void
    {
        $invoiceItemModel = config('filament-modular-subscriptions.models.invoice_item');

        if ($subscription->plan->is_pay_as_you_go) {
            foreach ($subscription->moduleUsages as $moduleUsage) {
                $invoiceItemModel::create([
                    'invoice_id' => $invoice->id,
                    'description' => __('filament-modular-subscriptions::fms.invoice.module_usage', ['module' => $moduleUsage->module->getName()]),
                    'quantity' => $moduleUsage->usage,
                    'unit_price' => $subscription->plan->modulePrice($moduleUsage->module),
                    'total' => $moduleUsage->pricing,
                ]);
            }
        } else {
            $invoiceItemModel::create([
                'invoice_id' => $invoice->id,
                'description' => __('filament-modular-subscriptions::fms.invoice.subscription_fee', ['plan' => $subscription->plan->trans_name]),
                'quantity' => 1,
                'unit_price' => $subscription->plan->price,
                'total' => $subscription->plan->price,
            ]);
        }
    }

    public function renewSubscription(Subscription $subscription): bool
    {
        $invoice = $this->generateInvoice($subscription);

        $subscription->renew();

        //@todo: trigger payment process (Stripe, etc...)

        $invoice->status = PaymentStatus::PAID;
        $invoice->paid_at = now();
        $invoice->save();

        return true;
    }
}
