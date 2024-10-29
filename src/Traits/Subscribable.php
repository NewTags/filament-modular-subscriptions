<?php

namespace HoceineEl\FilamentModularSubscriptions\Traits;

use Carbon\Carbon;
use Filament\Notifications\Actions\Action as NotificationAction;
use Filament\Notifications\Notification;
use HoceineEl\FilamentModularSubscriptions\Enums\Interval;
use HoceineEl\FilamentModularSubscriptions\Enums\SubscriptionStatus;
use HoceineEl\FilamentModularSubscriptions\Models\Plan;
use HoceineEl\FilamentModularSubscriptions\Models\Subscription;
use HoceineEl\FilamentModularSubscriptions\Pages\TenantSubscription;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Trait Subscribable
 * 
 * Provides subscription management functionality for models.
 * 
 * @property-read \HoceineEl\FilamentModularSubscriptions\Models\Subscription|null $subscription
 * @property-read \HoceineEl\FilamentModularSubscriptions\Models\Plan|null $plan
 * @property \Carbon\Carbon|null $trial_ends_at
 */
trait Subscribable
{
    /**
     * Get all subscriptions associated with the model.
     *
     * @return \Illuminate\Database\Eloquent\Relations\MorphMany
     */
    public function subscription(): MorphOne
    {
        $subscriptionModel = config('filament-modular-subscriptions.models.subscription');

        return $this->morphOne($subscriptionModel, 'subscribable');
    }

    /**
     * Get the current plan associated with the model through its active subscription.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasOneThrough
     */
    public function plan(): HasOneThrough
    {
        $planModel = config('filament-modular-subscriptions.models.plan');
        $subscriptionModel = config('filament-modular-subscriptions.models.subscription');

        return $this->hasOneThrough($planModel, $subscriptionModel, 'subscribable_id', 'id', 'id', 'plan_id');
    }

    /**
     * Get the currently active subscription for the model.
     *
     * @return \HoceineEl\FilamentModularSubscriptions\Models\Subscription|null
     */
    public function activeSubscription(): ?Subscription
    {
        return $this->subscription()
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

    /**
     * Check if the model has an active subscription.
     *
     * @return bool
     */
    public function hasSubscription(): bool
    {
        return $this->activeSubscription() !== null;
    }

    /**
     * Get the number of days left in the current subscription period including grace period.
     *
     * @return int|null
     */
    public function daysLeft(): ?int
    {
        $activeSubscription = $this->activeSubscription();
        if (! $activeSubscription) {
            return null;
        }

        $gracePeriodEndDate = $this->getGracePeriodEndDate($activeSubscription);

        return $gracePeriodEndDate
            ? number_format(now()->diffInDays($gracePeriodEndDate, false), 1)
            : null;
    }

    /**
     * Check if the subscription has expired.
     *
     * @return bool
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
     *
     * @return bool
     */
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

    /**
     * Renew the current subscription.
     *
     * @param int|null $days Number of days to renew for. If null, uses plan's default period.
     * @return bool
     */
    public function renew(?int $days = null): bool
    {
        $activeSubscription = $this->subscription;
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

    /**
     * Switch to a different subscription plan.
     *
     * @param int $newPlanId ID of the new plan
     * @return bool
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
            $activeSubscription->moduleUsages()->delete();

            $activeSubscription->update([
                'plan_id' => $newPlan->id,
                'starts_at' => now(),
                'ends_at' => $this->calculateEndDate($newPlan),
            ]);
        });

        return true;
    }

    public function shouldGenerateInvoice(): bool
    {
        $subscription = $this->subscription()->with('moduleUsages')->first();

        if (!$subscription) {
            return false;
        }

        $expired = $subscription->subscriber->isExpired();

        if ($subscription->plan->is_pay_as_you_go) {
            return $expired;
        }

        $moduleUsages = $subscription->moduleUsages;
        $anyModuleReachLimit = false;

        foreach ($moduleUsages as $moduleUsage) {
            $limit = $moduleUsage->module->planModules()->where('plan_id', $subscription->plan_id)->first()->limit;
            if ($limit !== null && $moduleUsage->usage >= $limit) {
                $anyModuleReachLimit = true;
                break;
            }
        }

        return $expired || $anyModuleReachLimit;
    }

    /**
     * Calculate the end date for a given plan.
     *
     * @param \HoceineEl\FilamentModularSubscriptions\Models\Plan $plan
     * @return \Carbon\Carbon
     */
    private function calculateEndDate(Plan $plan): Carbon
    {
        return now()->addDays($plan->period);
    }

    /**
     * Check if the model can use a specific module.
     *
     * @param string $moduleClass
     * @return bool
     */
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

        $canUse = $module->canUse($activeSubscription);

        if (!$canUse) {
            Notification::make()
                ->title(__('filament-modular-subscriptions::fms.messages.you_ve_reached_your_limit_for_this_module'))
                ->body(__('filament-modular-subscriptions::fms.messages.you_have_to_renew_your_subscription_to_use_this_module'))
                ->danger()
                ->actions([
                    NotificationAction::make('view_invoice')
                        ->label(__('filament-modular-subscriptions::fms.messages.view_invoice'))
                        ->url(fn() => TenantSubscription::getUrl())
                        ->openUrlInNewTab()
                        ->icon('heroicon-o-credit-card')
                        ->color('success')
                ])
                ->persistent()
                ->send();
        }
        return $canUse;
    }

    /**
     * Get the cache key for module access.
     *
     * @param string $moduleClass
     * @return string
     */
    public function getCacheKey(string $moduleClass): string
    {
        return "module_access_{$this->id}_{$moduleClass}";
    }

    /**
     * Get the usage count for a specific module.
     *
     * @param string $moduleClass
     * @return int|null
     */
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

    /**
     * Record usage for a specific module.
     *
     * @param string $moduleClass
     * @param int $quantity
     * @param bool $incremental
     * @throws \RuntimeException
     * @throws \InvalidArgumentException
     * @return void
     */
    public function recordUsage(string $moduleClass, int $quantity = 1, bool $incremental = true): void
    {
        $activeSubscription = $this->activeSubscription();
        if (! $activeSubscription) {
            throw new \RuntimeException('No active subscription found');
        }

        if (version_compare(app()->version(), '11.23', '>=')) {
            defer(function () use ($moduleClass, $quantity, $incremental, $activeSubscription) {
                $this->record($moduleClass, $quantity, $incremental, $activeSubscription);
            });
        } else {
            $this->record($moduleClass, $quantity, $incremental, $activeSubscription);
        }
    }

    public function record(string $moduleClass, int $quantity = 1, bool $incremental = true, Subscription $activeSubscription): void
    {
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

        // Log the usage record
        \Log::info("Module usage recorded for {$moduleClass}: {$moduleUsage->usage}.");

        Cache::forget($this->getCacheKey($moduleClass));
    }

    /**
     * Calculate pricing for module usage.
     *
     * @param \HoceineEl\FilamentModularSubscriptions\Models\Subscription $subscription
     * @param mixed $module
     * @param int $usage
     * @return float
     */
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

        return 0;
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

    /**
     * Create a new subscription for the model.
     *
     * @param \HoceineEl\FilamentModularSubscriptions\Models\Plan $plan
     * @param \Carbon\Carbon|null $startDate
     * @param \Carbon\Carbon|null $endDate
     * @param int|null $trialDays
     * @return \HoceineEl\FilamentModularSubscriptions\Models\Subscription
     */
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

    /**
     * Calculate usage for all modules.
     *
     * @return array<string, array<string, int|float>>
     */
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



    /**
     * Calculate total pricing including base plan and module usage.
     *
     * @return float
     */
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


    /**
     * Check if subscription is currently in trial period.
     *
     * @return bool
     */
    public function onTrial(): bool
    {
        return $this->activeSubscription() && $this->activeSubscription()->onTrial();
    }

    /**
     * Check if model has a generic trial period.
     *
     * @return bool
     */
    public function onGenericTrial(): bool
    {
        return $this->trial_ends_at && $this->trial_ends_at->isFuture();
    }

    /**
     * Get remaining trial days.
     *
     * @return int
     */
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

    /**
     * Extend trial period by specified number of days.
     *
     * @param int $days
     * @return void
     */
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

    /**
     * End trial period immediately.
     *
     * @return void
     */
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

    /**
     * Convert interval period to days.
     *
     * @param int $period
     * @param \HoceineEl\FilamentModularSubscriptions\Enums\Interval $interval
     * @return int
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
     *
     * @param \HoceineEl\FilamentModularSubscriptions\Models\Subscription $subscription
     * @return \Carbon\Carbon|null
     */
    private function getGracePeriodEndDate(Subscription $subscription = null): ?Carbon
    {
        $subscription = $subscription ?? $this->subscription;
        if (! $subscription->ends_at) {
            return null;
        }

        $gracePeriodDays = $subscription->plan->period_grace;

        return $subscription->ends_at->copy()->addDays($gracePeriodDays);
    }
}
