<?php

namespace HoceineEl\FilamentModularSubscriptions\Services;

use HoceineEl\FilamentModularSubscriptions\Enums\InvoiceStatus;
use HoceineEl\FilamentModularSubscriptions\Enums\SubscriptionStatus;
use HoceineEl\FilamentModularSubscriptions\Events\InvoiceGenerated;
use HoceineEl\FilamentModularSubscriptions\Models\Invoice;
use HoceineEl\FilamentModularSubscriptions\Models\Subscription;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class InvoiceService
{
    private string $invoiceModel;
    private string $invoiceItemModel;
    private float $taxPercentage;

    public function __construct()
    {
        $this->invoiceModel = config('filament-modular-subscriptions.models.invoice');
        $this->invoiceItemModel = config('filament-modular-subscriptions.models.invoice_item');
        $this->taxPercentage = config('filament-modular-subscriptions.tax_percentage', 15);
    }

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

            $this->updateInvoiceTotals($invoice);

            event(new InvoiceGenerated($invoice));

            $subscription->subscribable->notifySubscriptionChange('invoice_generated', [
                'invoice_id' => $invoice->id,
                'amount' => $invoice->total,
                'currency' => $subscription->plan->currency,
                'due_date' => $dueDate->format('Y-m-d')
            ]);

            return $invoice;
        });
    }

    protected function calculateDueDate(Subscription $subscription): Carbon
    {
        $plan = $subscription->plan;
        $startDate = $subscription->starts_at;

        if ($plan->due_days) {
            return now()->addDays($plan->due_days);
        }

        return $startDate->copy()->addDays($plan->invoice_period);
    }

    protected function hasCurrentPeriodInvoice(Subscription $subscription): bool
    {
        $plan = $subscription->plan;
        $today = now();

        if ($plan->fixed_invoice_day) {
            return $subscription->invoices()
                ->whereYear('created_at', $today->year)
                ->whereMonth('created_at', $today->month)
                ->exists();
        }

        $lastInvoice = $subscription->invoices()->latest()->first();
        if (!$lastInvoice) {
            return false;
        }

        $periodStart = $lastInvoice->created_at;
        $nextInvoiceDate = $this->calculateNextInvoiceDate($periodStart, $plan);

        return $today->lt($nextInvoiceDate);
    }

    private function calculateNextInvoiceDate(Carbon $periodStart, $plan): Carbon
    {
        return match ($plan->invoice_interval) {
            'day' => $periodStart->addDays($plan->invoice_period),
            'week' => $periodStart->addWeeks($plan->invoice_period),
            'month' => $periodStart->addMonths($plan->invoice_period),
            'year' => $periodStart->addYears($plan->invoice_period),
            default => $periodStart->addMonths(1),
        };
    }

    private function createInvoice(Subscription $subscription, Carbon $dueDate): Invoice
    {
        return $this->invoiceModel::create([
            'subscription_id' => $subscription->id,
            'tenant_id' => $subscription->subscribable_id,
            'amount' => 0,
            'tax' => 0,
            'status' => InvoiceStatus::UNPAID,
            'due_date' => $dueDate,
        ]);
    }

    private function createInvoiceItems(Invoice $invoice, Subscription $subscription): void
    {
        if ($this->isPayAsYouGo($subscription)) {
            $this->createPayAsYouGoItems($invoice, $subscription);
        } else {
            $this->createFixedPriceItem($invoice, $subscription);
        }
    }

    private function createPayAsYouGoItems(Invoice $invoice, Subscription $subscription): void
    {
        $subscription->load('moduleUsages');
        foreach ($subscription->moduleUsages as $moduleUsage) {
            $invoice->items()->create([
                'module_id' => $moduleUsage->module_id,
                'description' => __('filament-modular-subscriptions::fms.invoice.module_usage', [
                    'module' => $moduleUsage->module->getName(),
                ]),
                'quantity' => $moduleUsage->usage,
                'unit_price' => $subscription->plan->modulePrice($moduleUsage->module),
                'total' => $moduleUsage->pricing,
            ]);
        }
    }

    private function createFixedPriceItem(Invoice $invoice, Subscription $subscription): void
    {
        $invoice->items()->create([
            'description' => __('filament-modular-subscriptions::fms.invoice.subscription_fee', [
                'plan' => $subscription->plan->trans_name,
                'currency' => $subscription->plan->currency
            ]),
            'quantity' => 1,
            'unit_price' => $subscription->plan->price,
            'total' => $subscription->plan->price,
        ]);
    }

    private function updateInvoiceTotals(Invoice $invoice): void
    {
        $totalAmount = $invoice->items()->sum('total');
        $tax = $totalAmount * $this->taxPercentage / 100;

        $invoice->update([
            'amount' => $totalAmount + $tax,
            'tax' => $tax,
        ]);
    }

    private function isPayAsYouGo(Subscription $subscription): bool
    {
        return $subscription->plan->isPayAsYouGo();
    }

    public function generatePayAsYouGoInvoice(Subscription $subscription): void
    {
        $moduleUsages = $subscription->moduleUsages()
            ->get();

        if ($moduleUsages->isEmpty()) {
            return;
        }

        DB::transaction(function () use ($subscription, $moduleUsages) {
            $invoice = $this->createInvoice($subscription, now()->addDays(7));

            $this->processModuleUsages($invoice, $moduleUsages, $subscription);
            $this->updateInvoiceTotals($invoice);
        });
    }

    private function processModuleUsages(Invoice $invoice, $moduleUsages, Subscription $subscription): void
    {
        foreach ($moduleUsages->groupBy('module_id') as $moduleId => $usages) {
            $module = config('filament-modular-subscriptions.models.module')::find($moduleId);
            $totalUsage = $usages->sum('usage');

            $invoice->items()->create([
                'description' => $module->getLabel(),
                'quantity' => $totalUsage,
                'unit_price' => $subscription->plan->price,
                'total' => $totalUsage * $subscription->plan->price,
            ]);
        }
    }

    public function generateInitialPlanInvoice($tenant, $plan): Invoice
    {
        return DB::transaction(function () use ($tenant, $plan) {
            // Create subscription if it doesn't exist
            if (!$tenant->subscription) {
                $subscription = $this->createInitialSubscription($tenant, $plan);
            } else {
                $subscription = $tenant->subscription;
            }

            $invoice = $this->createInvoice(
                $subscription,
                now()->addDays(config('filament-modular-subscriptions.payment_due_days', 7))
            );

            $this->createFixedPriceItem($invoice, $subscription);
            $this->updateInvoiceTotals($invoice);

            return $invoice;
        });
    }

    protected function createInitialSubscription($tenant, $plan): Subscription
    {
        $subscriptionModel = config('filament-modular-subscriptions.models.subscription');

        return $subscriptionModel::create([
            'plan_id' => $plan->id,
            'subscribable_id' => $tenant->id,
            'subscribable_type' => get_class($tenant),
            'starts_at' => now(),
            'ends_at' => $this->calculateSubscriptionEndDate($plan),
            'trial_ends_at' => $plan->trial_period ? now()->addDays($plan->trial_period) : null,
            'status' => SubscriptionStatus::ON_HOLD,
        ]);
    }

    protected function calculateSubscriptionEndDate($plan): Carbon
    {
        return match ($plan->invoice_interval) {
            'day' => now()->addDays($plan->invoice_period),
            'week' => now()->addWeeks($plan->invoice_period),
            'month' => now()->addMonths($plan->invoice_period),
            'year' => now()->addYears($plan->invoice_period),
            default => now()->addMonth(),
        };
    }
}
