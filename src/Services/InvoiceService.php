<?php

namespace HoceineEl\FilamentModularSubscriptions\Services;

use HoceineEl\FilamentModularSubscriptions\Enums\InvoiceStatus;
use HoceineEl\FilamentModularSubscriptions\Enums\PaymentStatus;
use HoceineEl\FilamentModularSubscriptions\Models\Invoice;
use HoceineEl\FilamentModularSubscriptions\Models\Subscription;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class InvoiceService
{
    /**
     * Generate or update an invoice for the given subscription.
     *
     * @param Subscription $subscription
     * @return Invoice
     */
    public function generateInvoice(Subscription $subscription): ?Invoice
    {
        // Skip if invoice already exists for current period
        if ($this->hasCurrentPeriodInvoice($subscription)) {
            return null;
        }

        $dueDate = $this->calculateDueDate($subscription);
        
        DB::transaction(function () use ($subscription, $dueDate) {
            $invoice = $this->createInvoice($subscription, $dueDate);
            $this->createInvoiceItems($invoice, $subscription);
        });
    }

    /**
     * Calculate the due date for the invoice.
     *
     * @param Subscription $subscription
     * @return Carbon
     */
    protected function calculateDueDate(Subscription $subscription): Carbon
    {
        $plan = $subscription->plan;
        $startDate = $subscription->starts_at;
        
        // If plan has specific due days setting
        if ($plan->due_days) {
            return now()->addDays($plan->due_days);
        }
        
        // Default to subscription start date + invoice period
        return $startDate->copy()->addDays($plan->invoice_period);
    }

    /**
     * Check if an invoice already exists for the current period.
     *
     * @param Subscription $subscription
     * @return bool
     */
    protected function hasCurrentPeriodInvoice(Subscription $subscription): bool
    {
        return $subscription->invoices()
            ->where('created_at', '>=', now()->startOfMonth())
            ->exists();
    }

    /**
     * Create a new invoice.
     *
     * @param Subscription $subscription
     * @param Carbon $dueDate
     * @return Invoice
     */
    private function createInvoice(Subscription $subscription, Carbon $dueDate): Invoice
    {
        return $this->getInvoiceModel()::create([
            'subscription_id' => $subscription->id,
            'tenant_id' => $subscription->subscribable_id,
            'amount' => $this->calculateTotalAmount($subscription),
            'status' => InvoiceStatus::UNPAID,
            'due_date' => $dueDate,
        ]);
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
}
