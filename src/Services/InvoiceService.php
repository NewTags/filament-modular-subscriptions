<?php

namespace HoceineEl\FilamentModularSubscriptions\Services;

use HoceineEl\FilamentModularSubscriptions\Enums\InvoiceStatus;
use HoceineEl\FilamentModularSubscriptions\Enums\PaymentStatus;
use HoceineEl\FilamentModularSubscriptions\Models\Invoice;
use HoceineEl\FilamentModularSubscriptions\Models\Subscription;

class InvoiceService
{
    /**
     * Generate or update an invoice for the given subscription.
     *
     * @param Subscription $subscription
     * @return Invoice
     */
    public function generateInvoice(Subscription $subscription): Invoice
    {
        $invoice = $this->getInvoiceModel()::query()
            ->where('subscription_id', $subscription->id)
            ->where('status', InvoiceStatus::UNPAID)
            ->first();

        if ($invoice) {
            return $this->updateInvoice($invoice, $subscription);
        }

        return $this->createNewInvoice($subscription);
    }

    /**
     * Update an existing invoice.
     *
     * @param Invoice $invoice
     * @param Subscription $subscription
     * @return Invoice
     */
    private function updateInvoice(Invoice $invoice, Subscription $subscription): Invoice
    {
        $invoice->update([
            'amount' => $this->calculateTotalAmount($subscription),
            'due_date' => now()->addDays($this->getInvoiceDueDays()),
        ]);

        $invoice->items()->delete();

        $this->createInvoiceItems($invoice, $subscription);

        return $invoice->fresh();
    }

    /**
     * Create a new invoice.
     *
     * @param Subscription $subscription
     * @return Invoice
     */
    private function createNewInvoice(Subscription $subscription): Invoice
    {
        $invoice = $this->getInvoiceModel()::create([
            'subscription_id' => $subscription->id,
            'tenant_id' => $subscription->subscribable_id,
            'amount' => $this->calculateTotalAmount($subscription),
            'status' => PaymentStatus::UNPAID,
            'due_date' => now()->addDays($this->getInvoiceDueDays()),
        ]);

        $this->createInvoiceItems($invoice, $subscription);

        return $invoice;
    }

    /**
     * Calculate the total amount for the subscription.
     *
     * @param Subscription $subscription
     * @return float
     */
    private function calculateTotalAmount(Subscription $subscription): float
    {
        return $this->isPayAsYouGo($subscription)
            ? $subscription->moduleUsages()->sum('pricing')
            : $subscription->plan->price;
    }

    /**
     * Create invoice items based on the subscription's usage or fixed price.
     *
     * @param Invoice $invoice
     * @param Subscription $subscription
     * @return void
     */
    private function createInvoiceItems(Invoice $invoice, Subscription $subscription): void
    {
        $invoiceItemModel = $this->getInvoiceItemModel();

        if ($this->isPayAsYouGo($subscription)) {
            $subscription->load('moduleUsages');
            foreach ($subscription->moduleUsages as $moduleUsage) {
                $invoiceItemModel::create([
                    'invoice_id' => $invoice->id,
                    'description' => __('filament-modular-subscriptions::fms.invoice.module_usage', [
                        'module' => $moduleUsage->module->getName(),
                    ]),
                    'quantity' => $moduleUsage->usage,
                    'unit_price' => $subscription->plan->modulePrice($moduleUsage->module),
                    'total' => $moduleUsage->pricing,
                ]);
            }
        } else {
            $invoiceItemModel::create([
                'invoice_id' => $invoice->id,
                'description' => __('filament-modular-subscriptions::fms.invoice.subscription_fee', [
                    'plan' => $subscription->plan->trans_name,
                ]),
                'quantity' => 1,
                'unit_price' => $subscription->plan->price,
                'total' => $subscription->plan->price,
            ]);
        }
    }

    /**
     * Check if the subscription plan is pay-as-you-go.
     *
     * @param Subscription $subscription
     * @return bool
     */
    private function isPayAsYouGo(Subscription $subscription): bool
    {
        return $subscription->plan->is_pay_as_you_go;
    }

    /**
     * Get the invoice model from configuration.
     *
     * @return string
     */
    private function getInvoiceModel(): string
    {
        return config('filament-modular-subscriptions.models.invoice');
    }

    /**
     * Get the invoice item model from configuration.
     *
     * @return string
     */
    private function getInvoiceItemModel(): string
    {
        return config('filament-modular-subscriptions.models.invoice_item');
    }

    /**
     * Get the invoice due date in days from configuration.
     *
     * @return int
     */
    private function getInvoiceDueDays(): int
    {
        return config('filament-modular-subscriptions.invoice_due_date_days');
    }
}
