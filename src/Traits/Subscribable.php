<?php

namespace HoceineEl\FilamentModularSubscriptions\Traits;

use Carbon\Carbon;
use HoceineEl\FilamentModularSubscriptions\Enums\Interval;
use HoceineEl\FilamentModularSubscriptions\Enums\SubscriptionStatus;
use HoceineEl\FilamentModularSubscriptions\Models\Plan;
use HoceineEl\FilamentModularSubscriptions\Models\Subscription;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

trait Subscribable
{
    public function subscriptions(): MorphMany
    {
        $subscriptionModel = config('filament-modular-subscriptions.models.subscription');

        return $this->morphMany($subscriptionModel, 'subscribable');
    }

    public function plan(): HasOneThrough
    {
        $planModel = config('filament-modular-subscriptions.models.plan');
        $subscriptionModel = config('filament-modular-subscriptions.models.subscription');

        return $this->hasOneThrough($planModel, $subscriptionModel, 'subscribable_id', 'id', 'id', 'plan_id');
    }

    public function activeSubscription(): ?Subscription
    {
        return $this->subscriptions()
            ->whereDate('starts_at', '<=', now())
            ->where(function ($query) {
                $this->load('plan');
                $query->whereNull('ends_at')
                    ->orWhereDate('ends_at', '>', now())
                    ->orWhereDate('ends_at', '>=', now()->subDays($this->plan->period_grace));
            })
            ->where('status', SubscriptionStatus::ACTIVE)
            ->latest('starts_at')
            ->first();
    }

    public function hasSubscription(): bool
    {
        return $this->activeSubscription() !== null;
    }

    public function daysLeft(): ?int
    {
        $activeSubscription = $this->activeSubscription();
        if (! $activeSubscription) {
            return null;
        }

        $gracePeriodEndDate = $this->getGracePeriodEndDate($activeSubscription);

        return $gracePeriodEndDate
            ? now()->diffInDays($gracePeriodEndDate, false)
            : null;
    }

    public function isExpired(): bool
    {
        $activeSubscription = $this->activeSubscription();
        if (! $activeSubscription) {
            return true;
        }

        $gracePeriodEndDate = $this->getGracePeriodEndDate($activeSubscription);

        return $gracePeriodEndDate && $gracePeriodEndDate->isPast();
    }

    public function cancel(): bool
    {
        $activeSubscription = $this->activeSubscription();
        if (! $activeSubscription) {
            return false;
        }

        $activeSubscription->update([
            'status' => SubscriptionStatus::CANCELLED,
            'ends_at' => now(),
        ]);

        return true;
    }

    public function renew(?int $days = null): bool
    {
        $activeSubscription = $this->activeSubscription();
        if (! $activeSubscription) {
            return false;
        }

        $plan = $activeSubscription->plan;

        if ($days === null) {
            $days = $plan->period;
        }

        $newEndsAt = $activeSubscription->ends_at && $activeSubscription->ends_at->isFuture()
            ? $activeSubscription->ends_at->addDays($days)
            : now()->addDays($days);

        DB::transaction(function () use ($activeSubscription, $newEndsAt) {
            // Delete old usage data
            $activeSubscription->moduleUsages()->delete();

            $activeSubscription->update([
                'ends_at' => $newEndsAt,
                'starts_at' => now(),
            ]);
        });

        return true;
    }

    public function switchPlan(int $newPlanId): bool
    {
        $activeSubscription = $this->activeSubscription();
        if (! $activeSubscription) {
            return false;
        }

        $planModel = config('filament-modular-subscriptions.models.plan');
        $newPlan = $planModel::findOrFail($newPlanId);

        DB::transaction(function () use ($activeSubscription, $newPlan) {
            $activeSubscription->moduleUsages()->delete();

            $activeSubscription->update([
                'plan_id' => $newPlan->id,
                'starts_at' => now(),
                'ends_at' => $this->calculateEndDate($newPlan),
            ]);
        });

        return true;
    }

    private function calculateEndDate(Plan $plan): Carbon
    {
        return now()->addDays($plan->period);
    }

    public function canUseModule(string $moduleClass): bool
    {
        $activeSubscription = $this->activeSubscription();
        if (! $activeSubscription) {
            return false;
        }
        $moduleModel = config('filament-modular-subscriptions.models.module');
        $module = $moduleModel::where('class', $moduleClass)->first();

        if (! $module) {
            return false;
        }

        return $module->canUse($activeSubscription);
    }

    public function getCacheKey(string $moduleClass): string
    {
        return "module_access_{$this->id}_{$moduleClass}";
    }

    public function moduleUsage(string $moduleClass): ?int
    {
        $activeSubscription = $this->activeSubscription();
        if (! $activeSubscription) {
            return null;
        }

        $moduleModel = config('filament-modular-subscriptions.models.module');
        $module = $moduleModel::where('class', $moduleClass)->first();

        if (! $module) {
            return null;
        }
        $moduleUsage = $activeSubscription->moduleUsages()->where('module_id', $module->id)->first();
        if (! $moduleUsage) {
            return 0;
        }

        return $moduleUsage->usage;
    }

    public function recordUsage(string $moduleClass, int $quantity = 1, bool $incremental = true): void
    {
        $activeSubscription = $this->activeSubscription();
        if (! $activeSubscription) {
            throw new \RuntimeException('No active subscription found');
        }

        $moduleModel = config('filament-modular-subscriptions.models.module');
        $module = $moduleModel::where('class', $moduleClass)->first();

        if (! $module) {
            throw new \InvalidArgumentException("Module {$moduleClass} not found");
        }

        $moduleUsage = $activeSubscription->moduleUsages()
            ->where('module_id', $module->id)
            ->first();

        if (! $moduleUsage) {
            $moduleUsage = $activeSubscription->moduleUsages()->create([
                'module_id' => $module->id,
                'usage' => 0,
                'calculated_at' => now(),
            ]);
        }

        if ($incremental) {
            $moduleUsage->usage += $quantity;
        } else {
            $moduleUsage->usage = $quantity;
        }

        $moduleUsage->calculated_at = now();
        $moduleUsage->save();

        $pricing = $this->calculateModulePricing($activeSubscription, $module, $moduleUsage->usage);
        $moduleUsage->update(['pricing' => $pricing]);

        Cache::forget($this->getCacheKey($moduleClass));
    }

    private function calculateModulePricing(Subscription $subscription, $module, int $usage): float
    {

        $plan = $subscription->plan;
        $planModule = $plan->planModules()->where('module_id', $module->id)->first();

        if (! $planModule) {
            return 0;
        }

        if ($plan->is_pay_as_you_go) {
            return $usage * $planModule->price;
        }
        //@todo: add overuse pricing
        // else {
        //     $limit = $planModule->limit;
        //     if ($limit === null || $usage <= $limit) {
        //         return 0; // Included in the plan
        //     } else {
        //         return ($usage - $limit) * $planModule->price; // Charge for overuse
        //     }
        // }
    }

    public function subscribe(Plan $plan, ?Carbon $startDate = null, ?Carbon $endDate = null, ?int $trialDays = null): Subscription
    {
        $subscriptionModel = config('filament-modular-subscriptions.models.subscription');
        $startDate = $startDate ?? now();

        if ($endDate === null) {
            $days = $plan->period;
            $endDate = $startDate->copy()->addDays($days);
        }

        $subscription = new $subscriptionModel([
            'plan_id' => $plan->id,
            'subscribable_id' => $this->id,
            'subscribable_type' => get_class($this),
            'starts_at' => $startDate,
            'ends_at' => $endDate,
            'status' => SubscriptionStatus::ACTIVE,
        ]);

        if ($trialDays || $plan->period_trial > 0) {
            $trialDays = $trialDays ?? $plan->period_trial;
            $subscription->trial_ends_at = $startDate->copy()->addDays($trialDays);
        }

        $subscription->save();

        return $subscription;
    }

    public function calculateUsage(): array
    {
        $activeSubscription = $this->activeSubscription();
        if (! $activeSubscription) {
            return [];
        }

        $usage = [];

        $moduleModel = config('filament-modular-subscriptions.models.module');

        foreach ($moduleModel::get() as $module) {
            $moduleUsage = $module->calculateUsage($activeSubscription);
            $pricing = $this->calculateModulePricing($activeSubscription, $module, $moduleUsage);

            $usage[$module->name] = [
                'usage' => $moduleUsage,
                'pricing' => $pricing,
            ];

            $activeSubscription->moduleUsages()->updateOrCreate(
                ['module_id' => $module->id],
                [
                    'usage' => $moduleUsage,
                    'pricing' => $pricing,
                    'calculated_at' => now(),
                ]
            );
        }

        return $usage;
    }

    public function totalUsage(): float
    {
        $activeSubscription = $this->activeSubscription();
        if (! $activeSubscription) {
            return 0;
        }

        return $activeSubscription->moduleUsages()->sum('usage');
    }

    public function totalPricing(): float
    {
        $activeSubscription = $this->activeSubscription();
        if (! $activeSubscription) {
            return 0.0;
        }

        try {
            $basePlanPrice = $activeSubscription->plan->is_pay_as_you_go ? 0 : $activeSubscription->plan->price;
            $moduleUsagePricing = $activeSubscription->moduleUsages()->sum('pricing');

            return (float) number_format($basePlanPrice + $moduleUsagePricing, 2, '.', '');
        } catch (\Exception $e) {
            report($e);

            return 0.0;
        }
    }

    public function isOverLimit(int $limit): bool
    {
        return $this->totalUsage() > $limit;
    }

    public function onTrial(): bool
    {
        return $this->activeSubscription() && $this->activeSubscription()->onTrial();
    }

    public function onGenericTrial(): bool
    {
        return $this->trial_ends_at && $this->trial_ends_at->isFuture();
    }

    public function trialDaysLeft(): int
    {
        if ($this->onTrial()) {
            return $this->activeSubscription()->trial_ends_at->diffInDays(now());
        }

        if ($this->onGenericTrial()) {
            return $this->trial_ends_at->diffInDays(now());
        }

        return 0;
    }

    public function extendTrial(int $days): void
    {
        if ($this->onTrial()) {
            $subscription = $this->activeSubscription();
            $subscription->trial_ends_at = $subscription->trial_ends_at->addDays($days);
            $subscription->save();
        } elseif ($this->onGenericTrial()) {
            $this->trial_ends_at = $this->trial_ends_at->addDays($days);
            $this->save();
        }
    }

    public function endTrial(): void
    {
        if ($this->onTrial()) {
            $subscription = $this->activeSubscription();
            $subscription->trial_ends_at = now();
            $subscription->save();
        } elseif ($this->onGenericTrial()) {
            $this->trial_ends_at = now();
            $this->save();
        }
    }

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

    // Add a method to calculate the grace period end date
    private function getGracePeriodEndDate(Subscription $subscription): ?Carbon
    {
        if (! $subscription->ends_at) {
            return null;
        }

        $gracePeriodDays = $subscription->plan->period_grace;

        return $subscription->ends_at->copy()->addDays($gracePeriodDays);
    }
}
