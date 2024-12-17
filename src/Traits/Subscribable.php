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
                        $query->whereNull('ends_at')
                            ->orWhereDate('ends_at', '>', now())
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
                $activeSubscription = $this->activeSubscription();
                if (! $activeSubscription) {
                    return null;
                }

                $gracePeriodEndDate = $this->getGracePeriodEndDate($activeSubscription);

                return $gracePeriodEndDate
                    ? number_format(now()->diffInDays($gracePeriodEndDate, false))
                    : null;
            }
        );
    }

    /**
     * Check if the subscription has expired.
     */
    public function isExpired(): bool
    {
        $activeSubscription = $this->activeSubscription();
        if (! $activeSubscription) {
            return true;
        }

        $gracePeriodEndDate = $this->getGracePeriodEndDate($activeSubscription);

        return $gracePeriodEndDate && $gracePeriodEndDate->isPast();
    }

    /**
     * Cancel the current subscription.
     */
    public function cancel(): bool
    {
        $activeSubscription = $this->activeSubscription();
        if (! $activeSubscription) {
            return false;
        }

        DB::transaction(function () use ($activeSubscription) {
            $activeSubscription->update([
                'status' => SubscriptionStatus::CANCELLED,
                'ends_at' => now(),
            ]);
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
        $activeSubscription = $this->subscription;
        if (! $activeSubscription) {
            return false;
        }

        $plan = $activeSubscription->plan;

        DB::transaction(function () use ($activeSubscription, $days, $plan) {
            // Only delete non-persistent module usages
            $activeSubscription->moduleUsages()
                ->whereHas('module', function ($query) {
                    $query->where('is_persistent', false);
                })
                ->delete();

            $activeSubscription->update([
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
     * @param  int  $newPlanId  ID of the new plan
     */
    public function switchPlan(int $newPlanId): bool
    {
        $activeSubscription = $this->subscription;
        if (! $activeSubscription) {
            return false;
        }

        $planModel = config('filament-modular-subscriptions.models.plan');
        $newPlan = $planModel::findOrFail($newPlanId);

        DB::transaction(function () use ($activeSubscription, $newPlan) {

            if ($activeSubscription->is_pay_as_you_go) {
                $activeSubscription->moduleUsages()->delete();
            } elseif ($newPlan->is_pay_as_you_go && !$activeSubscription->plan->is_pay_as_you_go) {
                $activeSubscription->moduleUsages()->delete();
            }

            $activeSubscription->update([
                'plan_id' => $newPlan->id,
                'starts_at' => now(),
                'ends_at' => $this->calculateEndDate($newPlan),
            ]);

            $this->invalidateSubscriptionCache();
        });

        return true;
    }

    public function shouldGenerateInvoice(): bool
    {
        $subscription = $this->subscription()
            ->with([
                'moduleUsages.module.planModules' => function ($query) {
                    $query->select('plan_id', 'module_id', 'limit');
                },
                'plan:id,is_pay_as_you_go',
            ])
            ->first();

        if (! $subscription) {
            return false;
        }

        $expired = $subscription->subscriber->isExpired();

        if ($subscription->plan->is_pay_as_you_go) {
            return $expired;
        }

        foreach ($subscription->moduleUsages as $moduleUsage) {
            $planModule = $moduleUsage->module->planModules
                ->where('plan_id', $subscription->plan_id)
                ->first();

            if ($planModule && $planModule->limit !== null && $moduleUsage->usage >= $planModule->limit) {
                return true;
            }
        }

        return $expired;
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
     */
    public function subscribe(Plan $plan, ?Carbon $startDate = null, ?Carbon $endDate = null, ?int $trialDays = null): Subscription
    {
        $startDate = $startDate ?? now();

        if ($plan->isTrialPlan() && !$this->canUseTrial()) {
            throw new \Exception(__('filament-modular-subscriptions::fms.errors.trial_already_used'));
        }

        if ($endDate === null) {
            $endDate = $startDate->copy()->addDays($plan->period);
        }
        $status = $plan->is_pay_as_you_go
            ? SubscriptionStatus::ACTIVE
            : SubscriptionStatus::ON_HOLD;
        $subscription = $this->subscription()->create([
            'plan_id' => $plan->id,
            'starts_at' => $startDate,
            'ends_at' => $endDate,
            'status' => $status,
            'has_used_trial' => $plan->isTrialPlan() || $this->subscription?->has_used_trial,
        ]);

        if ($trialDays || $plan->period_trial > 0) {
            $trialDays = $trialDays ?? $plan->period_trial;
            $subscription->trial_ends_at = $startDate->copy()->addDays($trialDays);
            $subscription->save();
        }

        $this->invalidateSubscriptionCache();

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
}
