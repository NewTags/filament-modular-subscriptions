<?php

namespace HoceineEl\FilamentModularSubscriptions\Services;

use HoceineEl\FilamentModularSubscriptions\Enums\PaymentStatus;
use HoceineEl\FilamentModularSubscriptions\Models\Invoice;
use HoceineEl\FilamentModularSubscriptions\Models\InvoiceItem;
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
            'status' => 'pending',
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
                    'description' => __('filament-modular-subscriptions::modular-subscriptions.invoice.module_usage', ['module' => $moduleUsage->module->getName()]),
                    'quantity' => $moduleUsage->usage,
                    'unit_price' => $subscription->plan->modulePrice($moduleUsage->module),
                    'total' => $moduleUsage->pricing,
                ]);
            }
        } else {
            $invoiceItemModel::create([
                'invoice_id' => $invoice->id,
                'description' => __('filament-modular-subscriptions::modular-subscriptions.invoice.subscription_fee', ['plan' => $subscription->plan->trans_name]),
                'quantity' => 1,
                'unit_price' => $subscription->plan->price,
                'total' => $subscription->plan->price,
            ]);
        }
    }

    public function renewSubscription(Subscription $subscription, ?int $newPlanId = null): bool
    {
        $invoice = $this->generateInvoice($subscription);

        if ($newPlanId) {
            $subscription->plan_id = $newPlanId;
        }

        $subscription->starts_at = now();
        $subscription->ends_at = $subscription->starts_at->add($subscription->plan->invoice_interval);
        $subscription->save();

        //@todo: trigger payment process (Stripe, etc...)

        $invoice->status = PaymentStatus::PAID;
        $invoice->paid_at = now();
        $invoice->save();

        return true;
    }
}
