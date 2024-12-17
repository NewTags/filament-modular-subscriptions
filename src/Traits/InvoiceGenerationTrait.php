<?php

namespace NewTags\FilamentModularSubscriptions\Traits;

use Carbon\Carbon;
use NewTags\FilamentModularSubscriptions\Enums\Interval;
use NewTags\FilamentModularSubscriptions\Enums\InvoiceStatus;

trait InvoiceGenerationTrait
{
    protected function generateInvoice($subscription, $invoiceService, $logService): void
    {
        $this->info("Generating invoice for subscription {$subscription->subscribable->name}");

        if ($invoice = $invoiceService->generate($subscription)) {
            $this->info("Invoice generated successfully for subscription {$subscription->subscribable->name}");
            $this->updateSubscriptionStatus($subscription, $invoice, $logService);
            $subscription->subscribable->invalidateSubscriptionCache();
        }
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
        $plan = $subscription->plan;
        $baseDate = $lastInvoice?->created_at ?? $subscription->starts_at;

        $nextDate = match ($plan->invoice_interval) {
            Interval::DAY => $baseDate->copy()->addDays($plan->invoice_period),
            Interval::WEEK => $baseDate->copy()->addWeeks($plan->invoice_period),
            Interval::MONTH => $baseDate->copy()->addMonths($plan->invoice_period),
            Interval::YEAR => $baseDate->copy()->addYears($plan->invoice_period),
            default => throw new \InvalidArgumentException(
                "Invalid invoice interval: {$plan->invoice_interval->getLabel()}"
            ),
        };

        return $nextDate;
    }
} 