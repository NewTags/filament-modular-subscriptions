<?php

namespace HoceineEl\FilamentModularSubscriptions\Commands;

use HoceineEl\FilamentModularSubscriptions\Enums\PaymentStatus;
use HoceineEl\FilamentModularSubscriptions\Enums\SubscriptionStatus;
use HoceineEl\FilamentModularSubscriptions\Services\Concerns\PaymentService;
use HoceineEl\FilamentModularSubscriptions\Services\InvoiceService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProcessSubscriptionInvoices extends Command
{
    protected $signature = 'subscriptions:process-invoices';

    protected $description = 'Process invoices for expiring subscriptions';

    public function handle(InvoiceService $invoiceService, PaymentService $paymentService)
    {
        $this->info('Processing subscription invoices...');

        $subscriptionModel = config('filament-modular-subscriptions.models.subscription');
        $expiringSubscriptions = $subscriptionModel::query()
            ->where('ends_at', '<=', now()->addDays(config('filament-modular-subscriptions.invoice_due_date_days')))
            ->where('status', SubscriptionStatus::ACTIVE)
            ->get();

        foreach ($expiringSubscriptions as $subscription) {
            if (! $this->hasInvoiceForCurrentPeriod($subscription)) {
                $this->processSubscription($subscription, $invoiceService, $paymentService);
            } else {
                $this->info("Subscription {$subscription->id} already has an invoice for the current period. Skipping.");
            }
        }

        $this->info('Subscription invoices processed successfully.');
    }

    private function hasInvoiceForCurrentPeriod($subscription): bool
    {
        $currentPeriodStart = $subscription->ends_at->subDays($subscription->plan->period);

        return $subscription->invoices()
            ->where('created_at', '>=', $currentPeriodStart)
            ->exists();
    }

    private function processSubscription($subscription, InvoiceService $invoiceService, PaymentService $paymentService)
    {
        $this->info("Processing subscription {$subscription->id} for {$subscription->subscribable->name}");

        DB::beginTransaction();

        try {
            // Generate invoice
            $invoice = $invoiceService->generateInvoice($subscription);

            if (config('filament-modular-subscriptions.payment_enabled')) {
                // Attempt payment
                $paymentResult = $paymentService->processPayment($invoice);
            }
            if (app()->isLocal() && !config('filament-modular-subscriptions.payment_enabled')) {
                $paymentResult = random_int(0, 1) === 1;
            }
            if ($paymentResult->success) {
                $this->updateSubscriptionStatus($subscription, $invoice, SubscriptionStatus::ACTIVE);
                $this->info("Payment successful for subscription {$subscription->id}");
            } else {
                $this->updateSubscriptionStatus($subscription, $invoice, SubscriptionStatus::EXPIRED);
                $this->warn("Payment failed for subscription {$subscription->id}");
            }

            // Generate and send invoice PDF
            $invoiceService->generateAndSendInvoicePdf($invoice);

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Error processing subscription {$subscription->id}: " . $e->getMessage());
            $this->error("Error processing subscription {$subscription->id}: " . $e->getMessage());
        }
    }

    private function updateSubscriptionStatus($subscription, $invoice, SubscriptionStatus $status)
    {
        $subscription->status = $status;
        if ($status === SubscriptionStatus::ACTIVE) {
            $subscription->ends_at = now()->addDays($subscription->plan->period);
        }
        $subscription->save();

        $invoice->status = $status === SubscriptionStatus::ACTIVE ? PaymentStatus::PAID : PaymentStatus::UNPAID;
        $invoice->paid_at = $status === SubscriptionStatus::ACTIVE ? now() : null;
        $invoice->save();
    }
}
