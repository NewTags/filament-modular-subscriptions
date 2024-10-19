<?php

namespace HoceineEl\FilamentModularSubscriptions\Traits;

use Carbon\Carbon;
use HoceineEl\FilamentModularSubscriptions\Enums\Interval;
use HoceineEl\FilamentModularSubscriptions\Enums\SubscriptionStatus;
use HoceineEl\FilamentModularSubscriptions\Facades\ModularSubscriptions;
use HoceineEl\FilamentModularSubscriptions\Models\Plan;
use HoceineEl\FilamentModularSubscriptions\Models\Subscription;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Facades\DB;

trait Subscribable
{
    public function subscriptions(): MorphMany
    {
        $subscriptionModel = config('filament-modular-subscriptions.models.subscription');

        return $this->morphMany($subscriptionModel, 'subscribable');
    }

    public function activeSubscription(): ?Subscription
    {
        return $this->subscriptions()
            ->where('starts_at', '<=', now())
            ->where(function ($query) {
                $query->whereNull('ends_at')
                    ->orWhere('ends_at', '>', now());
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

        return $activeSubscription->ends_at
            ? now()->diffInDays($activeSubscription->ends_at, false)
            : null;
    }

    public function isExpired(): bool
    {
        $activeSubscription = $this->activeSubscription();
        if (! $activeSubscription) {
            return true;
        }

        return $activeSubscription->ends_at && $activeSubscription->ends_at->isPast();
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
            $days = $this->calculateDaysFromInterval($plan->invoice_period, $plan->invoice_interval);
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

    public function changePlan(int $newPlanId): bool
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
        $days = $this->calculateDaysFromInterval($plan->invoice_period, $plan->invoice_interval);

        return now()->addDays($days);
    }

    public function canUseModule(string $moduleName): bool
    {
        $activeSubscription = $this->activeSubscription();
        if (! $activeSubscription) {
            return false;
        }

        $module = ModularSubscriptions::getRegisteredModules()->get($moduleName);

        if (! $module) {
            return false;
        }

        return $module->canUse($activeSubscription);
    }

    public function recordUsage(string $moduleName, int $quantity = 1, bool $incremental = true): void
    {
        $activeSubscription = $this->activeSubscription();
        if (! $activeSubscription) {
            throw new \RuntimeException('No active subscription found');
        }

        $module = ModularSubscriptions::getRegisteredModules()->get($moduleName);

        if (! $module) {
            throw new \InvalidArgumentException("Module {$moduleName} not found");
        }

        $moduleUsage = $activeSubscription->moduleUsages()
            ->where('module_id', $module->getId())
            ->first();

        if (! $moduleUsage) {
            $moduleUsage = $activeSubscription->moduleUsages()->create([
                'module_id' => $module->getId(),
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

        $pricing = $module->getPricing($activeSubscription);
        $moduleUsage->update(['pricing' => $pricing]);
    }

    public function subscribe(Plan $plan, ?Carbon $startDate = null, ?Carbon $endDate = null, ?int $trialDays = null): Subscription
    {
        $subscriptionModel = config('filament-modular-subscriptions.models.subscription');
        $startDate = $startDate ?? now();

        if ($endDate === null) {
            $days = $this->calculateDaysFromInterval($plan->invoice_period, $plan->invoice_interval);
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

        if ($trialDays || $plan->trial_period > 0) {
            $trialDays = $trialDays ?? $this->calculateDaysFromInterval($plan->trial_period, $plan->trial_interval);
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

        foreach (ModularSubscriptions::getRegisteredModules() as $moduleName => $module) {
            $moduleUsage = $module->calculateUsage($activeSubscription);
            $pricing = $module->getPricing($activeSubscription);

            $usage[$moduleName] = [
                'usage' => $moduleUsage,
                'pricing' => $pricing,
            ];

            $activeSubscription->moduleUsages()->updateOrCreate(
                ['module_id' => $module->getId()],
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
            return 0;
        }

        return $activeSubscription->moduleUsages()->sum('pricing');
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
}
