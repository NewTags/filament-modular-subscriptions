<?php

namespace HoceineEl\FilamentModularSubscriptions\Services;

use HoceineEl\FilamentModularSubscriptions\Enums\InvoiceStatus;
use HoceineEl\FilamentModularSubscriptions\Events\InvoiceGenerated;
use HoceineEl\FilamentModularSubscriptions\Models\Invoice;
use HoceineEl\FilamentModularSubscriptions\Models\Subscription;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class InvoiceService
{
    /**
     * Generate or update an invoice for the given subscription.
     */
    public function generateInvoice(Subscription $subscription): ?Invoice
    {
        if ($this->hasCurrentPeriodInvoice($subscription)) {
            return null;
        }

        return $this->generate($subscription);
    }

    public function generate(Subscription $subscription): Invoice
    {
        $dueDate = $this->calculateDueDate($subscription);

        return DB::transaction(function () use ($subscription, $dueDate) {
            $invoice = $this->createInvoice($subscription, $dueDate);
            $this->createInvoiceItems($invoice, $subscription);

            // Calculate total amount from invoice items
            $totalAmount = $invoice->items()->sum('total');

            // Calculate and update tax
            $taxPercentage = config('filament-modular-subscriptions.tax_percentage', 15);
            $tax = $totalAmount * $taxPercentage / 100;

            // Update invoice with final amounts
            $invoice->update([
                'amount' => $totalAmount + $tax,
                'tax' => $tax,
            ]);

            // Fire event if needed
            event(new InvoiceGenerated($invoice));

            return $invoice;
        });
    }

    /**
     * Calculate the due date for the invoice.
     */
    protected function calculateDueDate(Subscription $subscription): Carbon
    {
        $plan = $subscription->plan;
        $startDate = $subscription->starts_at;

        if ($plan->due_days) {
            return now()->addDays($plan->due_days);
        }

        return $startDate->copy()->addDays($plan->invoice_period);
    }

    /**
     * Check if an invoice already exists for the current period.
     */
    protected function hasCurrentPeriodInvoice(Subscription $subscription): bool
    {
        $plan = $subscription->plan;
        $today = now();

        // If plan has fixed invoice day
        if ($plan->fixed_invoice_day) {
            return $subscription->invoices()
                ->whereYear('created_at', $today->year)
                ->whereMonth('created_at', $today->month)
                ->exists();
        }

        // Calculate the start of the current period
        $lastInvoice = $subscription->invoices()->latest()->first();
        if (! $lastInvoice) {
            return false;
        }

        $periodStart = $lastInvoice->created_at;
        $nextInvoiceDate = match ($plan->invoice_interval) {
            'day' => $periodStart->addDays($plan->invoice_period),
            'week' => $periodStart->addWeeks($plan->invoice_period),
            'month' => $periodStart->addMonths($plan->invoice_period),
            'year' => $periodStart->addYears($plan->invoice_period),
            default => $periodStart->addMonths(1),
        };

        return $today->lt($nextInvoiceDate);
    }

    /**
     * Create a new invoice.
     */
    private function createInvoice(Subscription $subscription, Carbon $dueDate): Invoice
    {
        return $this->getInvoiceModel()::create([
            'subscription_id' => $subscription->id,
            'tenant_id' => $subscription->subscribable_id,
            'amount' => 0, // Initial amount, will be updated after items
            'tax' => 0, // Initial tax, will be updated after items
            'status' => InvoiceStatus::UNPAID,
            'due_date' => $dueDate,
        ]);
    }

    /**
     * Calculate the total amount for the subscription.
     */
    private function calculateTotalAmount(Subscription $subscription): float
    {
        return $this->isPayAsYouGo($subscription)
            ? $subscription->moduleUsages()->sum('pricing')
            : $subscription->plan->price;
    }

    /**
     * Create invoice items based on the subscription's usage or fixed price.
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
     */
    private function isPayAsYouGo(Subscription $subscription): bool
    {
        return $subscription->plan->isPayAsYouGo();
    }

    /**
     * Get the invoice model from configuration.
     */
    private function getInvoiceModel(): string
    {
        return config('filament-modular-subscriptions.models.invoice');
    }

    /**
     * Get the invoice item model from configuration.
     */
    private function getInvoiceItemModel(): string
    {
        return config('filament-modular-subscriptions.models.invoice_item');
    }

    public function generatePayAsYouGoInvoice(Subscription $subscription): void
    {
        // Get all module usages since last invoice
        $moduleUsages = $subscription->moduleUsages()
            ->whereNull('invoiced_at')
            ->get();

        if ($moduleUsages->isEmpty()) {
            return;
        }

        // Create invoice
        $invoice = config('filament-modular-subscriptions.models.invoice')::create([
            'tenant_id' => $subscription->tenant_id,
            'subscription_id' => $subscription->id,
            'status' => InvoiceStatus::UNPAID,
            'due_date' => now()->addDays(7), // Or your preferred payment terms
            'issued_at' => now(),
        ]);

        // Create invoice items for each module usage
        foreach ($moduleUsages->groupBy('module_id') as $moduleId => $usages) {
            $module = config('filament-modular-subscriptions.models.module')::find($moduleId);
            $totalUsage = $usages->sum('usage');

            $invoice->items()->create([
                'module_id' => $moduleId,
                'description' => $module->getLabel() . ' Usage',
                'quantity' => $totalUsage,
                'unit_price' => $subscription->plan->price,
                'total' => $totalUsage * $subscription->plan->price,
            ]);

            // Mark usages as invoiced
            $usages->each->update(['invoiced_at' => now()]);
        }

        // Update invoice total
        $invoice->update([
            'total' => $invoice->items->sum('total'),
        ]);
    }
}
