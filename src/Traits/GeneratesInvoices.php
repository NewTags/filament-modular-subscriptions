<?php

namespace NewTags\FilamentModularSubscriptions\Traits;

use NewTags\FilamentModularSubscriptions\Enums\InvoiceStatus;
use NewTags\FilamentModularSubscriptions\Events\InvoiceGenerated;
use NewTags\FilamentModularSubscriptions\Models\Invoice;
use NewTags\FilamentModularSubscriptions\Models\Subscription;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use NewTags\FilamentModularSubscriptions\Models\Module;

trait GeneratesInvoices
{
    private function createInvoice(Subscription $subscription, Carbon $dueDate = null): Invoice
    {
        $dueDate = $dueDate ?? now()->addDays($subscription->plan->period_grace);
        return $this->invoiceModel::create([
            'subscription_id' => $subscription->id,
            'tenant_id' => $subscription->subscribable_id,
            'subtotal' => 0,
            'tax' => 0,
            'amount' => 0,
            'status' => InvoiceStatus::UNPAID,
            'due_date' => $dueDate,
        ]);
    }

    public function generateInvoice(Subscription $subscription): ?Invoice
    {
        if ($this->hasCurrentPeriodInvoice($subscription)) {
            return null;
        }

        return $this->generate($subscription);
    }

    public function generate(Subscription $subscription): ?Invoice
    {
        if ($subscription->onTrial()) {
            return null;
        }


        return DB::transaction(function () use ($subscription) {
            $invoice = $this->createInvoice($subscription);
            $this->createInvoiceItems($invoice, $subscription);
            $this->updateInvoiceTotals($invoice);

            event(new InvoiceGenerated($invoice));
            $this->notifyInvoiceGeneration($invoice, $subscription);

            return $invoice;
        });
    }

    private function notifyInvoiceGeneration(Invoice $invoice, Subscription $subscription): void
    {
        $subscribable = $subscription->subscribable;
        $subscribable->notifySubscriptionChange('invoice_generated', [
            'invoice_id' => $invoice->id,
            'subtotal' => $invoice->subtotal,
            'tax' => $invoice->tax,
            'amount' => $invoice->amount,
            'currency' => config('filament-modular-subscriptions.main_currency'),
            'due_date' => $invoice->due_date->format('Y-m-d')
        ]);

        $subscribable->notifySuperAdmins('invoice_generated', [
            'invoice_id' => $invoice->id,
            'amount' => $invoice->total,
            'tenant' => $subscription->subscribable->name,
            'currency' =>  config('filament-modular-subscriptions.main_currency')
        ]);

        $subscribable->clearFmsCache();
    }

    private function createInvoiceItems(Invoice $invoice, Subscription $subscription, $plan = null): void
    {
        $plan = $subscription->plan;
        if ($plan->is_pay_as_you_go) {
            $this->createPayAsYouGoItems($invoice, $subscription);
        } else {
            $this->createFixedPriceItem($invoice, $subscription, $plan);
        }

        $nonCancelledInvoicesCount = $subscription->invoices()
            ->where('status', '!=', InvoiceStatus::CANCELLED)
            ->count();

        if ($nonCancelledInvoicesCount === 0 && $plan->setup_fee > 0) {
            $invoice->items()->create([
                'description' => __('filament-modular-subscriptions::fms.invoice.setup_fee'),
                'total' => $plan->setup_fee,
                'quantity' => 1,
                'unit_price' => $plan->setup_fee,
            ]);
        }
    }

    private function createPayAsYouGoItems(Invoice $invoice, Subscription $subscription): void
    {
        $subscription->loadMissing('moduleUsages.module', 'plan');
        foreach ($subscription->moduleUsages as $moduleUsage) {
            /** @var Module $module */
            $module = $moduleUsage->module;
            $moduleInstance = $module->getInstance();
            $usage = $moduleInstance->calculateUsage($subscription);
            $unitPrice = $moduleInstance->getPrice($subscription);
            $total = $usage * $unitPrice;
            $label = $moduleInstance->getLabel();
            if ($total > 0) {
                $invoice->items()->create([
                    'description' => __('filament-modular-subscriptions::fms.invoice.module_usage', [
                        'module' => $label,
                    ]),
                    'quantity' => $usage,
                    'unit_price' => $unitPrice,
                    'total' => $total,
                ]);
            }

            if (!$moduleUsage->not_persistent) {
                $moduleUsage->delete();
            }
        }
    }

    private function createFixedPriceItem(Invoice $invoice, Subscription $subscription, $plan = null): void
    {
        $plan = $plan ?? $subscription->plan;
        $price = $plan->price;
        $invoice->items()->create([
            'description' => __('filament-modular-subscriptions::fms.invoice.subscription_fee', [
                'plan' => $plan->trans_name,
                'currency' => $plan->currency
            ]),
            'quantity' => 1,
            'unit_price' => $price,
            'total' => $price,
        ]);
    }

    private function updateInvoiceTotals(Invoice $invoice): Invoice
    {
        return DB::transaction(function () use ($invoice) {
            $items = $invoice->items;
            $subtotal = $items->sum('total');
            $tax = round($subtotal * ($this->taxPercentage / 100), 2);

            $invoice->update([
                'subtotal' => $subtotal,
                'tax' => $tax,
                'amount' => $subtotal + $tax,
            ]);

            return $invoice;
        });
    }
}
