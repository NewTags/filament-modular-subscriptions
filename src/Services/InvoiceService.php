<?php

namespace NewTags\FilamentModularSubscriptions\Services;

use NewTags\FilamentModularSubscriptions\Enums\InvoiceStatus;
use NewTags\FilamentModularSubscriptions\Enums\SubscriptionStatus;
use NewTags\FilamentModularSubscriptions\Events\InvoiceGenerated;
use NewTags\FilamentModularSubscriptions\Models\Invoice;
use NewTags\FilamentModularSubscriptions\Models\Subscription;
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

    public function generate(Subscription $subscription): ?Invoice
    {
        // Don't generate invoices for subscriptions on trial
        if ($subscription->onTrial()) {
            return null;
        }

        $dueDate = $this->calculateDueDate($subscription);

        return DB::transaction(function () use ($subscription, $dueDate) {
            $invoice = $this->createInvoice($subscription, $dueDate);
            $this->createInvoiceItems($invoice, $subscription);
            $this->updateInvoiceTotals($invoice);

            event(new InvoiceGenerated($invoice));
            $subscribable = $subscription->subscribable;
            $subscribable->notifySubscriptionChange('invoice_generated', [
                'invoice_id' => $invoice->id,
                'subtotal' => $invoice->subtotal,
                'tax' => $invoice->tax,
                'amount' => $invoice->amount,
                'currency' => $subscription->plan->currency,
                'due_date' => $dueDate->format('Y-m-d')
            ]);

            $subscribable->notifySuperAdmins('invoice_generated', [
                'invoice_id' => $invoice->id,
                'amount' => $invoice->total,
                'tenant' => $subscription->subscribable->name,
                'currency' => $subscription->plan->currency
            ]);
            $subscribable->invalidateSubscriptionCache();

            return $invoice;
        });
    }

    protected function calculateDueDate(Subscription $subscription): Carbon
    {
        return now()->addDays($subscription->plan->due_days);
    }

    protected function hasCurrentPeriodInvoice(Subscription $subscription): bool
    {
        $plan = $subscription->plan;
        $today = now();

        if ($plan->fixed_invoice_day > 0) {
            return $subscription->invoices()
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
        return match ($plan->invoice_interval->value) {
            'day' => $periodStart->addDays($plan->invoice_period),
            'week' => $periodStart->addWeeks($plan->invoice_period),
            'month' => $periodStart->addMonths($plan->invoice_period),
            'year' => $periodStart->addYears($plan->invoice_period),
            default => $periodStart->addMonth(),
        };
    }

    private function createInvoice(Subscription $subscription, Carbon $dueDate): Invoice
    {
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
        $subscription->load('moduleUsages.module');
        foreach ($subscription->moduleUsages as $moduleUsage) {
            $unitPrice = $subscription->plan->modulePrice($moduleUsage->module);
            $total = $moduleUsage->usage * $unitPrice;

            $invoice->items()->create([
                'description' => __('filament-modular-subscriptions::fms.invoice.module_usage', [
                    'module' => $moduleUsage->module->getName(),
                ]),
                'quantity' => $moduleUsage->usage,
                'unit_price' => $unitPrice,
                'total' => $total,
            ]);

            // Delete the usage after creating invoice item
            $moduleUsage->delete();
        }
    }

    private function createFixedPriceItem(Invoice $invoice, Subscription $subscription, $plan = null): void
    {
        $price = $plan ? $plan->price : $subscription->plan->price;
        $invoice->items()->create([
            'description' => __('filament-modular-subscriptions::fms.invoice.subscription_fee', [
                'plan' => $subscription->plan->trans_name,
                'currency' => $subscription->plan->currency
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

    private function isPayAsYouGo(Subscription $subscription): bool
    {
        return $subscription->plan->is_pay_as_you_go;
    }

    public function generatePayAsYouGoInvoice(Subscription $subscription): ?Invoice
    {
        $moduleUsages = $subscription->moduleUsages()
            ->get();

        if ($moduleUsages->isEmpty()) {
            return null;
        }

        return DB::transaction(function () use ($subscription, $moduleUsages) {
            $invoice = $this->createInvoice($subscription, now()->addDays(7));

            $this->processModuleUsages($invoice, $moduleUsages, $subscription);
            $this->updateInvoiceTotals($invoice);

            return $invoice;
        });
    }

    private function processModuleUsages(Invoice $invoice, $moduleUsages, Subscription $subscription): void
    {
        $moduleUsages->load('module');

        foreach ($moduleUsages->groupBy('module_id') as $moduleId => $usages) {
            $module = config('filament-modular-subscriptions.models.module')::find($moduleId);
            $totalUsage = $usages->sum('usage');
            $modulePrice = $subscription->plan->modulePrice($module);
            $total = $totalUsage * $modulePrice;

            $invoice->items()->create([
                'description' => $module->getLabel(),
                'quantity' => $totalUsage,
                'unit_price' => $modulePrice,
                'total' => $total,
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

            $this->createFixedPriceItem($invoice, $subscription, $plan);
            $this->updateInvoiceTotals($invoice);

            return $invoice;
        });
    }

    protected function createInitialSubscription($tenant, $plan): Subscription
    {
        $subscriptionModel = config('filament-modular-subscriptions.models.subscription');
        $startDate = now();

        return $subscriptionModel::create([
            'plan_id' => $plan->id,
            'subscribable_id' => $tenant->id,
            'subscribable_type' => get_class($tenant),
            'starts_at' => $startDate,
            'ends_at' => $this->calculateSubscriptionEndDate($plan),
            'trial_ends_at' => $this->calculateTrialEndDate($plan, $startDate),
            'status' => $plan->is_trial_plan ? SubscriptionStatus::ACTIVE : SubscriptionStatus::ON_HOLD,
            'has_used_trial' => $tenant->canUseTrial() && $plan->is_trial_plan,
        ]);
    }

    protected function calculateSubscriptionEndDate($plan): Carbon
    {
        return match ($plan->invoice_interval->value) {
            'day' => now()->addDays($plan->invoice_period),
            'week' => now()->addWeeks($plan->invoice_period),
            'month' => now()->addMonths($plan->invoice_period),
            'year' => now()->addYears($plan->invoice_period),
            default => now()->addMonth(),
        };
    }

    protected function calculateTrialEndDate($plan, Carbon $startDate): ?Carbon
    {
        if (!$plan->trial_period || !$plan->trial_interval) {
            return null;
        }

        return match ($plan->trial_interval->value) {
            'day' => $startDate->copy()->addDays($plan->trial_period),
            'week' => $startDate->copy()->addWeeks($plan->trial_period),
            'month' => $startDate->copy()->addMonths($plan->trial_period),
            'year' => $startDate->copy()->addYears($plan->trial_period),
            default => null,
        };
    }
}
