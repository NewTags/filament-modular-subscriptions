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

class ScheduleInvoiceGeneration extends Command
{
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

    protected function generateInvoice($subscription, InvoiceService $invoiceService, SubscriptionLogService $logService): void
    {
        $this->info("Generating invoice for subscription {$subscription->subscribable->name}");

        if ($invoice = $invoiceService->generate($subscription)) {
            $this->info("Invoice generated successfully for subscription {$subscription->subscribable->name}");
            $this->updateSubscriptionStatus($subscription, $invoice, $logService);
            $subscription->subscribable->invalidateSubscriptionCache();
        }
    }

    protected function updateSubscriptionStatus($subscription, $invoice, SubscriptionLogService $logService): void
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

    protected function handleError($subscription, SubscriptionLogService $logService, \Exception $e): void
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

    protected function shouldGenerateInvoice($subscription): bool
    {
        $plan = $subscription->plan;
        $lastInvoice = $subscription->invoices()->latest()->first();
        if ($plan->isTrialPlan()) {
            return false;
        }

        if ($this->hasUnpaidInvoices($subscription)) {
            return false;
        }

        $today = now();

        if ($plan->is_pay_as_you_go) {
            return $this->shouldGeneratePayAsYouGoInvoice($subscription, $plan, $today);
        }

        if (!$lastInvoice) {
            return true;
        }

        if ($plan->fixed_invoice_day) {
            return $this->shouldGenerateFixedDayInvoice($subscription, $plan, $today);
        }

        return $this->shouldGenerateIntervalInvoice($subscription, $lastInvoice, $today);
    }

    protected function hasUnpaidInvoices($subscription): bool
    {
        return $subscription->invoices()->where(function ($query) {
            $query->where('status', InvoiceStatus::PARTIALLY_PAID)
                ->orWhere('status', InvoiceStatus::UNPAID);
        })->exists();
    }

    protected function shouldGeneratePayAsYouGoInvoice($subscription, $plan, $today): bool
    {
        if (!$subscription->ends_at) {
            return false;
        }

        if ($today->gte($subscription->ends_at)) {
            return true;
        }

        if ($plan->fixed_invoice_day && $today->day == $plan->fixed_invoice_day) {
            return !$subscription->invoices()
                ->whereMonth('created_at', $today->month)
                ->exists();
        }

        return false;
    }

    protected function shouldGenerateFixedDayInvoice($subscription, $plan, $today): bool
    {
        if ($today->day != $plan->fixed_invoice_day) {
            return false;
        }

        if ($subscription->ends_at && $today->gte($subscription->ends_at)) {
            return !$subscription->invoices
                ->whereBetween('created_at', [
                    $subscription->ends_at->startOfMonth(),
                    $subscription->ends_at->endOfMonth()
                ])
                ->count();
        }

        return false;
    }

    protected function shouldGenerateIntervalInvoice($subscription, $lastInvoice, $today): bool
    {
        $nextInvoiceDate = $this->calculateNextInvoiceDate($subscription, $lastInvoice);
        return $today->gte($nextInvoiceDate);
    }

    protected function calculateNextInvoiceDate($subscription, $lastInvoice): Carbon
    {
        return $subscription->ends_at;
    }

    protected function isInvoiceOverdue(Invoice $invoice): bool
    {
        $totalPaid = $invoice->payments()
            ->where('status', PaymentStatus::PAID)
            ->sum('amount');

        return $totalPaid < $invoice->amount && now()->greaterThan($invoice->due_date);
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

    protected function handleSubscriptionNearExpiry($subscription): void
    {
        if (!$subscription->ends_at || $subscription->ends_at->diffInDays(now()) > 5) {
            return;
        }

        $notificationData = [
            'days' => number_format($subscription->ends_at->diffInDays(now())),
            'expiry_date' => $subscription->ends_at->format('Y-m-d'),
            'plan' => $subscription->plan->trans_name
        ];

        $subscription->subscribable->notifySubscriptionChange('subscription_near_expiry', $notificationData);

        if ($subscription->ends_at->diffInDays(now()) <= 3) {
            $adminNotificationData = array_merge($notificationData, [
                'tenant' => $subscription->subscribable->name
            ]);

            $subscription->subscribable->notifySuperAdmins('subscription_near_expiry', $adminNotificationData);
        }
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
