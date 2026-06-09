<?php

namespace NewTags\FilamentModularSubscriptions\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use NewTags\FilamentModularSubscriptions\Enums\InvoiceStatus;
use NewTags\FilamentModularSubscriptions\Events\InvoiceGenerated;
use NewTags\FilamentModularSubscriptions\Models\Invoice;
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
}
