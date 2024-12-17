<?php

namespace NewTags\FilamentModularSubscriptions\Traits;

use Illuminate\Support\Facades\Log;
use NewTags\FilamentModularSubscriptions\Enums\InvoiceStatus;

trait NotificationHandlerTrait
{
    protected function handleError($subscription, $logService, \Exception $e): void
    {
        $logService->log(
            $subscription,
            'invoice_generation_failed',
            __('filament-modular-subscriptions::fms.logs.invoice_generation_failed', [
                'error' => $e->getMessage()
            ]),
            null,
            null,
            ['error' => $e->getMessage()]
        );

        $this->notifyError($subscription, $e);
        $this->logError($subscription, $e);
    }

    protected function notifyError($subscription, \Exception $e): void
    {
        $subscription->subscribable->notifySuperAdmins('invoice_generation_failed', [
            'tenant' => $subscription->subscribable->name,
            'error' => $e->getMessage(),
            'subscription_id' => $subscription->id
        ]);

        $this->error("Error generating invoice for subscription {$subscription->subscribable->name}: {$e->getMessage()}");
    }

    protected function logError($subscription, \Exception $e): void
    {
        Log::error("Invoice generation error for subscription {$subscription->id}", [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
        ]);
    }

    protected function handlePastDueInvoice($subscription): void
    {
        $pastDueInvoices = $this->getPastDueInvoices($subscription);

        foreach ($pastDueInvoices as $invoice) {
            $this->notifyPastDueInvoice($subscription, $invoice);
        }
    }

    protected function getPastDueInvoices($subscription)
    {
        return $subscription->invoices()
            ->whereIn('status', [InvoiceStatus::UNPAID, InvoiceStatus::PARTIALLY_PAID])
            ->whereDate('due_date', '<', now())
            ->get();
    }

    protected function notifyPastDueInvoice($subscription, $invoice): void
    {
        $daysOverdue = number_format(now()->diffInDays($invoice->due_date));
        $notificationData = [
            'invoice_id' => $invoice->id,
            'days' => $daysOverdue,
            'amount' => $invoice->total,
            'currency' => $subscription->plan->currency
        ];

        $subscription->subscribable->notifySubscriptionChange('invoice_overdue', $notificationData);

        $adminNotificationData = array_merge($notificationData, [
            'tenant' => $subscription->subscribable->name
        ]);

        $subscription->subscribable->notifySuperAdmins('invoice_overdue', $adminNotificationData);
    }
} 