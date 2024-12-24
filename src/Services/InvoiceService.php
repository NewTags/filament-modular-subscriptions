<?php

namespace NewTags\FilamentModularSubscriptions\Services;

use NewTags\FilamentModularSubscriptions\Models\Invoice;
use NewTags\FilamentModularSubscriptions\Models\Subscription;
use NewTags\FilamentModularSubscriptions\Traits\GeneratesInvoices;
use NewTags\FilamentModularSubscriptions\Traits\ManagesSubscriptions;
use Illuminate\Support\Facades\DB;
use NewTags\FilamentModularSubscriptions\Models\Module;

class InvoiceService
{
    use GeneratesInvoices, ManagesSubscriptions;

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
        $moduleUsages = $subscription->moduleUsages()->get();
        if ($moduleUsages->isEmpty()) {
            return null;
        }

        return DB::transaction(function () use ($subscription, $moduleUsages) {
            $invoice = $this->createInvoice($subscription);
            $this->createInvoiceItems($invoice, $subscription);
            $this->updateInvoiceTotals($invoice);

            return $invoice;
        });
    }

    private function processModuleUsages(Invoice $invoice, $moduleUsages, Subscription $subscription): void
    {
        $moduleUsages->loadMissing('module');

        foreach ($moduleUsages->groupBy('module_id') as $moduleId => $usages) {
            /** @var Module $module */
            $module = config('filament-modular-subscriptions.models.module')::find($moduleId);
            $moduleInstance = $module->getInstance();
            $totalUsage = $moduleInstance->calculateUsage($subscription);
            $modulePrice = $moduleInstance->getPrice($subscription);
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
            if (!$tenant->subscription) {
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
}
