<?php

namespace NewTags\FilamentModularSubscriptions\Modules;

use NewTags\FilamentModularSubscriptions\Enums\InvoiceStatus;
use NewTags\FilamentModularSubscriptions\Enums\SubscriptionStatus;
use NewTags\FilamentModularSubscriptions\FmsPlugin;
use NewTags\FilamentModularSubscriptions\Models\Subscription;

class BaseModule
{
    public function getName(): string
    {
        return 'Base Module';
    }

    public function getLabelKey(): string
    {
        return 'base_module';
    }

    public function calculateUsage(Subscription $subscription): int
    {
        // Implement your usage calculation logic here
        return FmsPlugin::getTenant()->moduleUsage(get_class($this));
    }

    public function getPrice(Subscription $subscription): float
    {

        $subscription->loadMissing('plan');
        return $subscription->plan->modulePrice(get_class($this));
    }

    public function canUse(Subscription $subscription): bool
    {
        // Check if subscription is on hold or pending payment
        if ($subscription->status === SubscriptionStatus::ON_HOLD || $subscription->status === SubscriptionStatus::PENDING_PAYMENT) {
            if ($subscription->ends_at->isPast()) {
                $latestInvoice = $subscription->invoices()->whereIn('status', [InvoiceStatus::UNPAID, InvoiceStatus::PARTIALLY_PAID])->latest()->first();
                if ($latestInvoice && $latestInvoice->due_date->isPast()) {
                    return false;
                } elseif ($latestInvoice && $latestInvoice->due_date->isFuture()) {
                    return true;
                }
            } else {
                return false;
            }
        }
        if ($subscription->is_pay_as_you_go) {
            return true;
        }

        if ($subscription->plan->moduleLimit(get_class($this)) == 0) {
            return true;
        }

        $usage = $this->calculateUsage($subscription);
        $limit = $subscription->plan->moduleLimit(get_class($this));

        // Allow usage if in grace period and under limit
        if ($subscription->isInGracePeriod() && $usage < $limit) {
            return true;
        }

        return $usage < $limit;
    }

    public function getLabel(): string
    {
        return __($this->getLabelKey());
    }
}
