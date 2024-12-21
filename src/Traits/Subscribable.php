<?php

namespace NewTags\FilamentModularSubscriptions\Traits;

use Carbon\Carbon;
use NewTags\FilamentModularSubscriptions\Enums\Interval;
use NewTags\FilamentModularSubscriptions\Enums\SubscriptionStatus;
use NewTags\FilamentModularSubscriptions\Models\Plan;
use NewTags\FilamentModularSubscriptions\Models\Subscription;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Builder;
use Filament\Notifications\Notification;
use NewTags\FilamentModularSubscriptions\Models\Invoice;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use NewTags\FilamentModularSubscriptions\Services\InvoiceService;
use NewTags\FilamentModularSubscriptions\Enums\InvoiceStatus;

/**
 * Trait Subscribable
 *
 * Provides subscription management functionality for models.
 *
 * @property-read \NewTags\FilamentModularSubscriptions\Models\Subscription|null $subscription
 * @property-read \NewTags\FilamentModularSubscriptions\Models\Plan|null $plan
 * @property \Carbon\Carbon|null $trial_ends_at
 */
trait Subscribable
{
    use HasSubscriptionNotifications;
    use HasSubscriptionModules;
    use HasTrialSubscription;
    /**
     * Cache key prefix for active subscription
     */
    private const ACTIVE_SUBSCRIPTION_CACHE_KEY = 'active_subscription_';

    private const CACHE_TTL = 1800; // 30 minutes in seconds

    private const DAYS_LEFT_CACHE_TTL = 86400; // 24 hours in seconds


    /**
     * Get all subscriptions associated with the model.
     *
     * @return \Illuminate\Database\Eloquent\Relations\MorphMany
     */
    public function subscription(): MorphOne
    {
        $subscriptionModel = config('filament-modular-subscriptions.models.subscription');

        return $this->morphOne($subscriptionModel, 'subscribable')->latest('starts_at');
    }

    /**
     * Get the current plan associated with the model through its active subscription.
     */
    public function plan(): HasOneThrough
    {
        $planModel = config('filament-modular-subscriptions.models.plan');
        $subscriptionModel = config('filament-modular-subscriptions.models.subscription');

        return $this->hasOneThrough($planModel, $subscriptionModel, 'subscribable_id', 'id', 'id', 'plan_id');
    }

    /**
     * Get the currently active subscription for the model with eager loaded relationships.
     */
    public function activeSubscription(): ?Subscription
    {
        $cacheKey = self::ACTIVE_SUBSCRIPTION_CACHE_KEY . $this->id;

        return Cache::remember(
            $cacheKey,
            self::CACHE_TTL,
            function () {
                return $this->subscription()
                    ->with(['plan', 'moduleUsages.module']) // Eager load relationships
                    ->whereDate('starts_at', '<=', now())
                    ->where(function ($query) {
                        $this->load('plan');
                        $query
                            ->whereDate('ends_at', '>', now())
                            ->orWhereDate('ends_at', '>=', now()->subDays(
                                $this->plan?->period_grace ?? 0
                            ));
                    })
                    ->where('status', SubscriptionStatus::ACTIVE)
                    ->first();
            }
        );
    }

    /**
     * Invalidate active subscription cache
     */
    public function invalidateSubscriptionCache(): void
    {
        // Clear active subscription cache
        Cache::forget(self::ACTIVE_SUBSCRIPTION_CACHE_KEY . $this->id);

        // Clear days left cache
        Cache::forget('subscription_days_left_' . $this->id);

        // Clear subscription alerts cache
        Cache::forget('subscription_alerts_' . $this->id);

        // Clear module access caches
        $moduleModel = config('filament-modular-subscriptions.models.module');
        foreach ($moduleModel::all() as $module) {
            Cache::forget($this->getCacheKey($module->class));
        }
    }

    /**
     * Check if the model has an active subscription.
     */
    public function hasSubscription(): bool
    {
        return $this->activeSubscription() !== null;
    }

    /**
     * Get the number of days left in the current subscription period including grace period.
     */
    public function daysLeft(): ?int
    {
        $cacheKey = 'subscription_days_left_' . $this->id;

        return Cache::remember(
            $cacheKey,
            self::DAYS_LEFT_CACHE_TTL,
            function () {
                return $this->subscription->daysLeft();
            }
        );
    }

    /**
     * Check if the subscription has expired.
     */
    public function isExpired(): bool
    {
        return $this->subscription->isExpired();
    }

    /**
     * Check if the subscription is in the grace period.
     */
    public function isInGracePeriod(): bool
    {
        return $this->subscription->isInGracePeriod();
    }

    /**
     * Cancel the current subscription.
     */
    public function cancel(): bool
    {
        $activeSubscription = $this->subscription;
        if (!$activeSubscription) {
            return false;
        }

        // Check for unpaid pay-as-you-go invoices
        if ($activeSubscription->plan->is_pay_as_you_go && $this->unpaidInvoices()->exists()) {
            Notification::make()
                ->title(__('filament-modular-subscriptions::fms.notifications.subscription.cancelled.failed'))
                ->body(__('filament-modular-subscriptions::fms.notifications.subscription.cancelled.unpaid_invoices'))
                ->danger()
                ->send();
            return false;
        }

        DB::transaction(function () use ($activeSubscription) {
            // Generate final invoice for pay-as-you-go plans
            if ($activeSubscription->plan->is_pay_as_you_go) {
                $invoiceService = app(InvoiceService::class);
                $finalInvoice = $invoiceService->generatePayAsYouGoInvoice($activeSubscription);

                if ($finalInvoice) {
                    $this->notifySuperAdmins('invoice_generated', [
                        'invoice_id' => $finalInvoice->id,
                        'amount' => $finalInvoice->amount,
                        'currency' => $finalInvoice->currency,
                    ]);
                }
                $activeSubscription->moduleUsages()->delete();
            }
            // Clean up pending invoices when cancelling a non-PAYG plan
            if (!$activeSubscription->plan->is_pay_as_you_go) {
                $pendingInvoices = $this->unpaidInvoices()
                    ->get();

                foreach ($pendingInvoices as $invoice) {
                    $invoice->update(['status' => InvoiceStatus::CANCELLED]);
                }
            }
            $activeSubscription->update([
                'status' => SubscriptionStatus::CANCELLED,
                'ends_at' => now(),
                'plan_id' => null,
            ]);

            // Clean up module usages

            $this->invalidateSubscriptionCache();
        });

        return true;
    }

    /**
     * Renew the current subscription.
     *
     * @param  int|null  $days  Number of days to renew for. If null, uses plan's default period.
     */
    public function renew(?int $days = null): bool
    {
        $subscription = $this->subscription;
        if (! $subscription) {
            return false;
        }

        $plan = $subscription->plan;

        DB::transaction(function () use ($subscription, $days, $plan) {
            // Only delete non-persistent module usages
            // Clean up module usages based on plan type
            if ($subscription->is_pay_as_you_go) {
                $subscription->moduleUsages()
                    ->delete();
            } else {
                $subscription->moduleUsages()
                    ->whereHas('module', function ($query) {
                        $query->where('is_persistent', false);
                    })
                    ->delete();
            }

            $subscription->update([
                'ends_at' => $this->calculateEndDate($plan, $days),
                'starts_at' => now(),
                'status' => SubscriptionStatus::ACTIVE,
                'trial_ends_at' => null,
            ]);

            $this->invalidateSubscriptionCache();
        });

        return true;
    }

    /**
     * Switch to a different subscription plan.
     *
     * @param int $newPlanId ID of the new plan
     * @param SubscriptionStatus|null $status Optional status for the new subscription
     * @return bool
     */
    public function switchPlan(int $newPlanId, ?SubscriptionStatus $status = null): bool
    {
        $activeSubscription = $this->subscription;
        if (!$activeSubscription) {
            return false;
        }

        $planModel = config('filament-modular-subscriptions.models.plan');
        $newPlan = $planModel::findOrFail($newPlanId);
        $oldPlan = $activeSubscription->plan;
        $invoiceService = app(InvoiceService::class);

        DB::transaction(function () use ($activeSubscription, $newPlan, $oldPlan, $status, $invoiceService) {
            // Handle existing subscription if pay-as-you-go
            if ($activeSubscription->plan->is_pay_as_you_go) {
                $finalInvoice = $invoiceService->generatePayAsYouGoInvoice($activeSubscription);

                $activeSubscription->update([
                    'status' => SubscriptionStatus::ON_HOLD
                ]);

                if ($finalInvoice) {
                    $this->notifySuperAdmins('invoice_generated', [
                        'invoice_id' => $finalInvoice->id,
                        'amount' => $finalInvoice->amount,
                        'currency' => $finalInvoice->currency,
                    ]);
                } else {
                    $this->notifySuperAdmins('invoice_generation_failed', [
                        'error' => 'Failed to generate final invoice for pay-as-you-go plan',
                    ]);
                }
            }

            // Clean up pending invoices when switching from non-PAYG plan
            if (($oldPlan && $oldPlan->id != $newPlan->id) && !$oldPlan->is_pay_as_you_go) {
                $pendingInvoices = $this->invoices()
                    ->whereIn('status', [InvoiceStatus::UNPAID, InvoiceStatus::PARTIALLY_PAID, InvoiceStatus::REFUNDED])
                    ->where('subscription_id', $activeSubscription->id)
                    ->get();

                foreach ($pendingInvoices as $invoice) {
                    $invoice->update(['status' => InvoiceStatus::CANCELLED]);
                    $this->notifySuperAdmins('invoice_cancelled', [
                        'invoice_id' => $invoice->id,
                        'reason' => 'Plan switch',
                    ]);
                }
            }

            // Clean up module usages based on plan type
            if ($activeSubscription->is_pay_as_you_go) {
                $activeSubscription->moduleUsages()->delete();
            } elseif ($newPlan->is_pay_as_you_go && !$activeSubscription->plan->is_pay_as_you_go) {
                $activeSubscription->moduleUsages()->delete();
            }

            // Update subscription
            $activeSubscription->update([
                'plan_id' => $newPlan->id,
                'starts_at' => now(),
                'ends_at' => $this->calculateEndDate($newPlan),
                'status' => $status ?? $activeSubscription->status,
            ]);

            // Generate initial invoice for non-trial plans
            if (!$newPlan->is_trial_plan && !$newPlan->is_pay_as_you_go) {
                $initialInvoice = $invoiceService->generateInitialPlanInvoice($this, $newPlan);
                $this->notifySuperAdmins('invoice_generated', [
                    'invoice_id' => $initialInvoice->id,
                    'amount' => $initialInvoice->amount,
                    'currency' => $initialInvoice->currency,
                ]);
            }

            // Send notification for subscription switch
            $this->notifySubscriptionChange('subscription_switched', [
                'plan' => $newPlan->trans_name,
                'date' => now()->format('Y-m-d H:i:s')
            ]);

            $this->invalidateSubscriptionCache();
        });

        return true;
    }



    /**
     * Calculate the end date for a given plan.
     */
    private function calculateEndDate(Plan $plan): Carbon
    {
        return now()->addDays($plan->period);
    }



    protected function getNextSuitablePlan(): ?Plan
    {
        $currentPlan = $this->subscription?->plan;
        if (! $currentPlan) {
            return null;
        }

        $plans = Plan::query();
        if ($plans->where('is_active', true)->where('id', '!=', $currentPlan->id)->count() === 0) {
            return null;
        }

        $nextPricePlan = Plan::where('price', '>', $currentPlan->price)
            ->where('is_active', true)
            ->orderBy('price')
            ->first();

        if (! $nextPricePlan) {
            $payAsYouGoPlan = Plan::where('is_pay_as_you_go', true)
                ->where('is_active', true)
                ->first();

            return $payAsYouGoPlan;
        }

        return $nextPricePlan;
    }



    /**
     * Create a new subscription for the model.
     *
     * @param Plan $plan The plan to subscribe to
     * @param Carbon|null $startDate Optional start date
     * @param Carbon|null $endDate Optional end date
     * @param int|null $trialDays Optional trial days
     * @return Subscription|null
     */
    public function subscribe(Plan $plan, ?Carbon $startDate = null, ?Carbon $endDate = null, ?int $trialDays = null): ?Subscription
    {
        $startDate = $startDate ?? now();

        // Check trial eligibility
        if ($plan->isTrialPlan() && !$this->canUseTrial()) {
            Notification::make()
                ->title(__('filament-modular-subscriptions::fms.notifications.subscription.trial.you_cant_use_trial'))
                ->danger()
                ->send();
            return null;
        }

        if ($endDate === null) {
            $endDate = $startDate->copy()->addDays($plan->period);
        }

        // Determine initial status
        $status = match (true) {
            $plan->is_pay_as_you_go => SubscriptionStatus::ACTIVE,
            $plan->is_trial_plan => SubscriptionStatus::ACTIVE,
            default => SubscriptionStatus::ON_HOLD,
        };

        DB::transaction(function () use (&$subscription, $plan, $startDate, $endDate, $status, $trialDays) {
            $subscription = $this->subscription()->create([
                'plan_id' => $plan->id,
                'starts_at' => $startDate,
                'ends_at' => $endDate,
                'status' => $status,
                'has_used_trial' => $plan->isTrialPlan() || $this->subscription?->has_used_trial,
            ]);
            // Handle trial period
            if ($trialDays || $plan->period_trial > 0) {
                $trialDays = $trialDays ?? $plan->period_trial;
                $subscription->trial_ends_at = $startDate->copy()->addDays($trialDays);
                $subscription->save();
            }
            $this->refresh();
            // Generate initial invoice for non-trial, non-PAYG plans
            if (!$plan->is_trial_plan && !$plan->is_pay_as_you_go) {
                $invoiceService = app(InvoiceService::class);
                $initialInvoice = $invoiceService->generateInitialPlanInvoice($this, $plan);

                if ($initialInvoice) {
                    $this->notifySuperAdmins('invoice_generated', [
                        'invoice_id' => $initialInvoice->id,
                        'amount' => $initialInvoice->amount,
                        'currency' => $initialInvoice->currency,
                    ]);
                } else {
                    $this->notifySuperAdmins('invoice_generation_failed', [
                        'error' => 'Failed to generate initial invoice for plan',
                    ]);
                }
            }

            // Send appropriate notifications
            if ($plan->is_pay_as_you_go) {
                $this->notifySubscriptionChange('started', [
                    'plan' => $plan->trans_name,
                    'type' => 'pay_as_you_go',
                    'trial' => $subscription->onTrial(),
                ]);
            } elseif ($plan->is_trial_plan) {
                $this->notifySubscriptionChange('trial', [
                    'plan' => $plan->trans_name,
                    'status' => $status->getLabel(),
                    'trial' => true,
                    'date' => now()->format('Y-m-d H:i:s')
                ]);
            }

            $this->invalidateSubscriptionCache();
        });
        return $subscription;
    }


    /**
     * Convert interval period to days.
     *
     * @throws \InvalidArgumentException
     */
    private function calculateDaysFromInterval(int $period, Interval $interval): int
    {
        switch ($interval) {
            case Interval::DAY:
                return $period;
            case Interval::WEEK:
                return $period * 7;
            case Interval::MONTH:
                return $period * 30;
            case Interval::YEAR:
                return $period * 365;
            default:
                throw new \InvalidArgumentException('Invalid interval');
        }
    }

    /**
     * Calculate the end date including grace period.
     */
    private function getGracePeriodEndDate(?Subscription $subscription = null): ?Carbon
    {
        $subscription = $subscription ?? $this->subscription;
        if (! $subscription->ends_at) {
            return null;
        }

        $gracePeriodDays = $subscription->plan->period_grace;

        return $subscription->ends_at->copy()->addDays($gracePeriodDays);
    }



    public function invoices(): HasMany
    {
        return $this->subscription?->invoices();
    }

    public function unpaidInvoices(): HasMany
    {
        return $this->invoices()->where(function ($query) {
            $query->where('status', '!=', InvoiceStatus::PAID)
                ->where('status', '!=', InvoiceStatus::CANCELLED);
        });
    }
}
