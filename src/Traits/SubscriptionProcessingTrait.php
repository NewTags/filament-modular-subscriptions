<?php

namespace NewTags\FilamentModularSubscriptions\Traits;

use NewTags\FilamentModularSubscriptions\Enums\SubscriptionStatus;
use NewTags\FilamentModularSubscriptions\Enums\InvoiceStatus;
use NewTags\FilamentModularSubscriptions\Enums\PaymentStatus;

trait SubscriptionProcessingTrait
{
    protected function processActiveSubscriptions($invoiceService, $logService): void
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

    protected function processSubscription($subscription, $invoiceService, $logService): void
    {
        try {
            if ($subscription->plan->isTrialPlan() && $subscription->ends_at && $subscription->ends_at->isPast()) {
                $this->handleTrialExpiration($subscription, $logService);
                return;
            }

            $this->handlePastDueInvoice($subscription);

            if ($this->shouldGenerateInvoice($subscription)) {
                $this->generateInvoice($subscription, $invoiceService, $logService);
            }
        } catch (\Exception $e) {
            $this->handleError($subscription, $logService, $e);
        }
    }

    protected function updateSubscriptionStatus($subscription, $invoice, $logService): void
    {
        $oldStatus = $subscription->status;
        $subscription->update(['status' => SubscriptionStatus::PENDING_PAYMENT]);

        $logService->log(
            $subscription,
            'invoice_generated',
            __('filament-modular-subscriptions::fms.logs.invoice_generated', [
                'invoice_id' => $invoice->id,
                'amount' => $invoice->total,
            ]),
            $oldStatus->value,
            SubscriptionStatus::PENDING_PAYMENT->value,
            [
                'invoice_id' => $invoice->id,
                'total' => $invoice->total,
                'items_count' => $invoice->items->count(),
            ]
        );
    }

    protected function handleTrialExpiration($subscription, $logService): void
    {
        $oldStatus = $subscription->status;
        $subscription->update([
            'status' => SubscriptionStatus::EXPIRED,
            'trial_ends_at' => null,
            'has_used_trial' => true,
        ]);

        $logService->log(
            $subscription,
            'trial_expired',
            __('filament-modular-subscriptions::fms.logs.trial_expired'),
            $oldStatus->value,
            SubscriptionStatus::EXPIRED->value
        );

        $subscription->subscribable->notifySubscriptionChange('trial_expired', [
            'plan' => $subscription->plan->trans_name,
            'expiry_date' => $subscription->ends_at->format('Y-m-d')
        ]);
    }
} 