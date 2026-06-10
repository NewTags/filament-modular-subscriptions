<?php

namespace NewTags\FilamentModularSubscriptions\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use NewTags\FilamentModularSubscriptions\Enums\InvoiceStatus;
use NewTags\FilamentModularSubscriptions\Enums\PaymentStatus;
use NewTags\FilamentModularSubscriptions\Events\InvoiceGenerated;
use NewTags\FilamentModularSubscriptions\FmsPlugin;
use NewTags\FilamentModularSubscriptions\Models\Invoice;
use NewTags\FilamentModularSubscriptions\Models\Payment;
use NewTags\FilamentModularSubscriptions\Models\Subscription;
use NewTags\FilamentModularSubscriptions\Traits\GeneratesInvoices;
use NewTags\FilamentModularSubscriptions\Traits\ManagesSubscriptions;

class InvoiceService
{
    use GeneratesInvoices;
    use ManagesSubscriptions;

    private string $invoiceModel;

    private string $invoiceItemModel;

    private float $taxPercentage;

    public function __construct()
    {
        $this->invoiceModel = config('filament-modular-subscriptions.models.invoice');
        $this->invoiceItemModel = config('filament-modular-subscriptions.models.invoice_item');
        $this->taxPercentage = config('filament-modular-subscriptions.tax_percentage', 15);
    }

    public function generatePayAsYouGoInvoice(Subscription $subscription): ?Invoice
    {
        $modules = $subscription->plan->modules;
        if ($modules->isEmpty()) {
            return null;
        }

        return DB::transaction(function () use ($subscription) {
            $invoice = $this->createInvoice($subscription);
            $this->createInvoiceItems($invoice, $subscription);
            $this->updateInvoiceTotals($invoice);

            return $invoice;
        });
    }

    public function generateInitialPlanInvoice($tenant, $plan): Invoice
    {
        return DB::transaction(function () use ($tenant, $plan) {
            if (! $tenant->subscription) {
                $subscription = $this->createInitialSubscription($tenant, $plan);
            } else {
                $subscription = $tenant->subscription;
            }
            $subscription->loadMissing('plan');
            $subscription->refresh();
            $invoice = $this->createInvoice(
                $subscription
            );

            $this->createInvoiceItems($invoice, $subscription, $plan);
            $invoice->refresh();
            $this->updateInvoiceTotals($invoice);

            return $invoice;
        });
    }

    /**
     * Compute the line items the automatic invoice generation would produce for a
     * subscription, without persisting anything. Used to pre-fill the manual invoice
     * form so it mirrors how invoices are auto-calculated for academies.
     *
     * @return array<int, array{description: string, quantity: float|int, unit_price: float}>
     */
    public function previewInvoiceItems(Subscription $subscription): array
    {
        $plan = $subscription->plan;

        if (! $plan) {
            return [];
        }

        $items = [];

        if ($plan->is_pay_as_you_go) {
            $subscription->loadMissing('plan.modules');

            foreach ($plan->modules as $module) {
                $moduleInstance = $module->getInstance();
                $usage = $moduleInstance->calculateUsage($subscription);
                $unitPrice = $moduleInstance->getPrice($subscription);

                if ($usage * $unitPrice > 0) {
                    $items[] = [
                        'description' => __('filament-modular-subscriptions::fms.invoice.module_usage', ['module' => $moduleInstance->getLabel()]),
                        'quantity' => $usage,
                        'unit_price' => round((float) $unitPrice, 2),
                    ];
                }
            }
        } else {
            $items[] = [
                'description' => __('filament-modular-subscriptions::fms.invoice.subscription_fee', ['plan' => $plan->trans_name, 'currency' => $plan->currency]),
                'quantity' => 1,
                'unit_price' => round((float) $plan->price, 2),
            ];
        }

        $hasNoInvoicesYet = $subscription->invoices()
            ->where('status', '!=', InvoiceStatus::CANCELLED)
            ->doesntExist();

        if ($hasNoInvoicesYet && $plan->setup_fee > 0) {
            $items[] = [
                'description' => __('filament-modular-subscriptions::fms.invoice.setup_fee'),
                'quantity' => 1,
                'unit_price' => round((float) $plan->setup_fee, 2),
            ];
        }

        return $items;
    }

    /**
     * Create a manual invoice for a subscription with arbitrary line items and an
     * editable tax percentage (e.g. an ad-hoc charge or fine).
     *
     * @param  array<int, array{description: string, quantity?: int|float, unit_price?: int|float}>  $items
     */
    public function createManualInvoice(
        Subscription $subscription,
        array $items,
        ?float $taxPercentage = null,
        ?Carbon $dueDate = null,
    ): Invoice {
        $taxPercentage = $taxPercentage ?? $this->taxPercentage;

        return DB::transaction(function () use ($subscription, $items, $taxPercentage, $dueDate): Invoice {
            $invoice = $this->invoiceModel::create([
                'subscription_id' => $subscription->id,
                'tenant_id' => $subscription->subscribable_id,
                'subtotal' => 0,
                'tax' => 0,
                'amount' => 0,
                'status' => InvoiceStatus::UNPAID,
                'due_date' => $dueDate ?? now()->addDays($subscription->plan->period_grace ?? 0),
            ]);

            foreach ($items as $item) {
                $quantity = (float) ($item['quantity'] ?? 1);
                $unitPrice = (float) ($item['unit_price'] ?? 0);

                $invoice->items()->create([
                    'description' => $item['description'],
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'total' => round($quantity * $unitPrice, 2),
                ]);
            }

            $subtotal = round((float) $invoice->items()->sum('total'), 2);
            $tax = round($subtotal * ($taxPercentage / 100), 2);

            $invoice->update([
                'subtotal' => $subtotal,
                'tax' => $tax,
                'amount' => round($subtotal + $tax, 2),
            ]);

            $invoice->refresh();

            event(new InvoiceGenerated($invoice));

            $subscribable = $subscription->subscribable;
            $subscribable->notifySubscriptionChange('invoice_generated', [
                'invoice_id' => $invoice->id,
                'subtotal' => $invoice->subtotal,
                'tax' => $invoice->tax,
                'amount' => $invoice->amount,
                'currency' => config('filament-modular-subscriptions.main_currency'),
                'due_date' => $invoice->due_date->format('Y-m-d'),
            ]);
            $subscribable->clearFmsCache();

            return $invoice;
        });
    }

    /**
     * Transition an invoice based on its confirmed (PAID) payments and run the
     * one-time paid side-effects (renewal, after-paid callback, notifications).
     *
     * Idempotent and concurrency-safe: the invoice row is locked and the renewal
     * side-effects fire only the first time the invoice becomes fully paid, so a
     * replayed or concurrent settlement can never renew the subscription twice.
     * MUST be called inside a database transaction.
     *
     * @return bool true if the invoice newly transitioned to paid/partially paid in this call
     */
    public function settleInvoice(Invoice $invoice, ?Payment $triggeredBy = null): bool
    {
        $invoice = $invoice->newQuery()->lockForUpdate()->findOrFail($invoice->getKey());
        $invoiceWasPaid = $invoice->status === InvoiceStatus::PAID;
        $subscription = $invoice->subscription;
        $currency = config('filament-modular-subscriptions.main_currency');

        $totalPaid = (float) $invoice->payments()
            ->where('status', PaymentStatus::PAID)
            ->sum('amount');

        // Amount of the payment that triggered this settlement; fall back to the total
        // confirmed payments when settled without a specific payment context (never 0).
        $paymentAmount = $triggeredBy !== null ? (float) $triggeredBy->amount : $totalPaid;

        if ($totalPaid >= $invoice->amount) {
            $invoice->update([
                'status' => InvoiceStatus::PAID,
                'paid_at' => $invoice->paid_at ?? now(),
            ]);

            if ($invoiceWasPaid) {
                return false;
            }

            FmsPlugin::runAfterInvoicePaid($invoice);

            if ($subscription && $subscription->plan_id) {
                $oldPlanId = $subscription->plan_id;
                $subscription->renew();

                if ($subscription->plan_id !== $oldPlanId) {
                    $subscription->subscribable->notifySubscriptionChange('subscription_switched', [
                        'old_plan' => $oldPlanId,
                        'new_plan' => $subscription->plan_id,
                        'start_date' => $subscription->starts_at->format('Y-m-d'),
                        'end_date' => $subscription->ends_at->format('Y-m-d'),
                        'currency' => $currency,
                        'amount' => $invoice->amount,
                    ]);
                } elseif ($subscription->wasRecentlyCreated) {
                    $subscription->subscribable->notifySubscriptionChange('subscription_activated', [
                        'plan' => $subscription->plan_id,
                        'start_date' => $subscription->starts_at->format('Y-m-d'),
                        'end_date' => $subscription->ends_at->format('Y-m-d'),
                        'currency' => $currency,
                        'amount' => $invoice->amount,
                    ]);
                } else {
                    $subscription->subscribable->notifySubscriptionChange('subscription_renewed', [
                        'plan' => $subscription->plan_id,
                        'start_date' => $subscription->starts_at->format('Y-m-d'),
                        'end_date' => $subscription->ends_at->format('Y-m-d'),
                        'currency' => $currency,
                        'amount' => $invoice->amount,
                    ]);
                }
            }

            if ($subscription) {
                $subscription->subscribable->notifySubscriptionChange('payment_received', [
                    'amount' => $paymentAmount,
                    'subtotal' => $invoice->subtotal,
                    'tax' => $invoice->tax,
                    'total' => $invoice->amount,
                    'currency' => $currency,
                    'invoice_id' => $invoice->id,
                    'status' => PaymentStatus::PAID->getLabel(),
                    'date' => now()->format('Y-m-d H:i:s'),
                ]);
            }

            return true;
        }

        if ($totalPaid > 0) {
            $invoice->update(['status' => InvoiceStatus::PARTIALLY_PAID]);

            if ($subscription) {
                $subscription->subscribable->notifySubscriptionChange('payment_partially_approved', [
                    'amount' => $paymentAmount,
                    'remaining' => round($invoice->amount - $totalPaid, 2),
                    'subtotal' => $invoice->subtotal,
                    'tax' => $invoice->tax,
                    'total' => $invoice->amount,
                    'currency' => $currency,
                    'status' => PaymentStatus::PARTIALLY_PAID->getLabel(),
                    'date' => now()->format('Y-m-d H:i:s'),
                ]);
            }

            return true;
        }

        return false;
    }
}
