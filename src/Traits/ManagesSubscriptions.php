<?php

namespace NewTags\FilamentModularSubscriptions\Traits;

use NewTags\FilamentModularSubscriptions\Models\Invoice;
use NewTags\FilamentModularSubscriptions\Models\Subscription;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

trait ManagesSubscriptions
{
    protected function calculateDueDate(Subscription $subscription): Carbon
    {
        return now()->addDays($subscription->plan->period_grace);
    }

    protected function hasCurrentPeriodInvoice(Subscription $subscription): bool
    {
        $plan = $subscription->plan;
        $today = now();

        if ($plan->fixed_invoice_day > 0) {
            return $subscription->invoices()
                ->whereMonth('created_at', $today->month)
                ->exists();
        }

        $lastInvoice = $subscription->invoices()->latest()->first();
        if (!$lastInvoice) {
            return false;
        }

        $periodStart = $lastInvoice->created_at;
        $nextInvoiceDate = $this->calculateNextInvoiceDate($periodStart, $plan);

        return $today->lt($nextInvoiceDate);
    }

    private function calculateNextInvoiceDate(Carbon $periodStart, $plan): Carbon
    {
        return match ($plan->invoice_interval->value) {
            'day' => $periodStart->addDays($plan->invoice_period),
            'week' => $periodStart->addWeeks($plan->invoice_period),
            'month' => $periodStart->addMonths($plan->invoice_period),
            'year' => $periodStart->addYears($plan->invoice_period),
            default => $periodStart->addMonth(),
        };
    }

    private function isPayAsYouGo(Subscription $subscription): bool
    {
        return $subscription->plan->is_pay_as_you_go;
    }

    protected function createInitialSubscription($tenant, $plan): ?Subscription
    {
        return $tenant->subscribe(
            plan: $plan,
            startDate: now(),
            endDate: $this->calculateSubscriptionEndDate($plan),
            trialDays: $plan->trial_period
        );
    }

    protected function calculateSubscriptionEndDate($plan): Carbon
    {
        return match ($plan->invoice_interval->value) {
            'day' => now()->addDays($plan->invoice_period),
            'week' => now()->addWeeks($plan->invoice_period),
            'month' => now()->addMonths($plan->invoice_period),
            'year' => now()->addYears($plan->invoice_period),
            default => now()->addMonth(),
        };
    }

    protected function calculateTrialEndDate($plan, Carbon $startDate): ?Carbon
    {
        if (!$plan->trial_period || !$plan->trial_interval) {
            return null;
        }

        return match ($plan->trial_interval->value) {
            'day' => $startDate->copy()->addDays($plan->trial_period),
            'week' => $startDate->copy()->addWeeks($plan->trial_period),
            'month' => $startDate->copy()->addMonths($plan->trial_period),
            'year' => $startDate->copy()->addYears($plan->trial_period),
            default => null,
        };
    }
}