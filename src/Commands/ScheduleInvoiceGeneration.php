<?php

namespace NewTags\FilamentModularSubscriptions\Commands;

use NewTags\FilamentModularSubscriptions\Models\Invoice;
use NewTags\FilamentModularSubscriptions\Enums\PaymentStatus;
use NewTags\FilamentModularSubscriptions\Enums\InvoiceStatus;
use NewTags\FilamentModularSubscriptions\Enums\Interval;
use NewTags\FilamentModularSubscriptions\Enums\SubscriptionStatus;
use NewTags\FilamentModularSubscriptions\Services\InvoiceService;
use NewTags\FilamentModularSubscriptions\Services\SubscriptionLogService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use NewTags\FilamentModularSubscriptions\Commands\Concerns\ShouldHandleExpiredSubscriptions;
use NewTags\FilamentModularSubscriptions\Commands\Concerns\CanGenerateInvoices;

class ScheduleInvoiceGeneration extends Command
{
    use ShouldHandleExpiredSubscriptions, CanGenerateInvoices;
    protected $signature = 'fms:schedule-invoices';

    protected $description = 'Generate invoices for subscriptions based on their billing cycles';

    public function handle(InvoiceService $invoiceService, SubscriptionLogService $logService)
    {
        $this->info('Starting invoice generation process');

        $this->processActiveSubscriptions($invoiceService, $logService);

        $this->info('Invoice generation process completed');
    }

    protected function processActiveSubscriptions(InvoiceService $invoiceService, SubscriptionLogService $logService): void
    {
        $subscriptionModel = config('filament-modular-subscriptions.models.subscription');

        $subscriptionModel::query()
            ->where('status', SubscriptionStatus::ACTIVE)
            ->with(['plan', 'invoices', 'subscribable'])
            ->chunk(100, function ($subscriptions) use ($invoiceService, $logService) {
                foreach ($subscriptions as $subscription) {
                    $this->processSubscription($subscription, $invoiceService, $logService);
                }
            });
    }

    protected function processSubscription($subscription, InvoiceService $invoiceService, SubscriptionLogService $logService): void
    {
        try {
            // Handle trial plan expiration
            if ($subscription->plan->isTrialPlan() && $subscription->ends_at && $subscription->ends_at->isPast()) {
                $this->handleTrialExpiration($subscription, $logService);
                return;
            }



            $this->handlePastDueInvoice($subscription);
            // $this->handleSubscriptionNearExpiry($subscription);

            if ($this->shouldGenerateInvoice($subscription)) {
                $this->generateInvoice($subscription, $invoiceService, $logService);
            }
        } catch (\Exception $e) {
            $this->handleError($subscription, $logService, $e);
        }
    }
}
