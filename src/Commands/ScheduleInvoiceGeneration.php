<?php

namespace NewTags\FilamentModularSubscriptions\Commands;

use Illuminate\Console\Command;
use NewTags\FilamentModularSubscriptions\Commands\Concerns\CanGenerateInvoices;
use NewTags\FilamentModularSubscriptions\Commands\Concerns\ShouldHandleExpiredSubscriptions;
use NewTags\FilamentModularSubscriptions\Enums\SubscriptionStatus;
use NewTags\FilamentModularSubscriptions\Models\FmsSetting;
use NewTags\FilamentModularSubscriptions\Services\InvoiceService;
use NewTags\FilamentModularSubscriptions\Services\SubscriptionLogService;

class ScheduleInvoiceGeneration extends Command
{
    use CanGenerateInvoices;
    use ShouldHandleExpiredSubscriptions;

    protected $signature = 'fms:schedule-invoices {--force : Run even when automatic invoice generation is disabled}';

    protected $description = 'Generate invoices for subscriptions based on their billing cycles';

    public function handle(InvoiceService $invoiceService, SubscriptionLogService $logService)
    {
        if (! $this->option('force') && ! FmsSetting::autoInvoiceGenerationEnabled()) {
            $this->info('Automatic invoice generation is disabled — skipping. Use --force to override.');

            return;
        }

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
                    filament()->setTenant($subscription->subscribable, true);
                    $this->processSubscription($subscription, $invoiceService, $logService);
                    filament()->setTenant(null, true);
                }
            });
    }

    protected function processSubscription($subscription, InvoiceService $invoiceService, SubscriptionLogService $logService): void
    {
        try {
            if (! $subscription->subscribable) {
                $this->warn("Skipping subscription {$subscription->id}: tenant no longer exists.");

                return;
            }

            // Handle trial plan expiration
            if ($subscription->plan && $subscription->plan->isTrialPlan() && $subscription->ends_at && $subscription->ends_at->isPast()) {
                $this->handleTrialExpiration($subscription, $logService);

                return;
            }

            if (config('filament-modular-subscriptions.notifications.enable_past_due_invoice_notification', false)) {
                $this->handlePastDueInvoice($subscription);
            }
            if (config('filament-modular-subscriptions.notifications.enable_subscription_near_expiry_notification', false)) {
                $this->handleSubscriptionNearExpiry($subscription);
            }

            if ($this->shouldGenerateInvoice($subscription)) {
                $this->generateInvoice($subscription, $invoiceService, $logService);
            }
        } catch (\Throwable $e) {
            $this->handleError($subscription, $logService, $e);
        }
    }
}
