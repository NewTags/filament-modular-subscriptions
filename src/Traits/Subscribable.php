<?php

namespace HoceineEl\FilamentModularSubscriptions\Traits;

use Carbon\Carbon;
use HoceineEl\FilamentModularSubscriptions\Enums\Interval;
use HoceineEl\FilamentModularSubscriptions\Enums\SubscriptionStatus;
use HoceineEl\FilamentModularSubscriptions\Models\Plan;
use HoceineEl\FilamentModularSubscriptions\Models\Subscription;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Builder;
use Filament\Notifications\Notification;

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
     * Cache key prefix for active subscription
     */
    private const ACTIVE_SUBSCRIPTION_CACHE_KEY = 'active_subscription_';

    private const CACHE_TTL = 1800; // 30 minutes in seconds

    private const DAYS_LEFT_CACHE_TTL = 86400; // 24 hours in seconds

    private const MODULE_ACCESS_CACHE_TTL = 1800; // 30 minutes in seconds

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
    private function invalidateSubscriptionCache(): void
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
            $activeSubscription->moduleUsages()->delete();

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

    /**
     * Check if the model can use a specific module.
     */
    public function canUseModule(string $moduleClass): bool
    {
        $cacheKey = $this->getCacheKey($moduleClass);

        return Cache::remember(
            $cacheKey,
            self::MODULE_ACCESS_CACHE_TTL,
            function () use ($moduleClass) {
                $activeSubscription = $this->activeSubscription();
                if (! $activeSubscription) {
                    return false;
                }

                $moduleModel = config('filament-modular-subscriptions.models.module');
                /** @var \HoceineEl\FilamentModularSubscriptions\Models\Module $module */
                $module = $moduleModel::where('class', $moduleClass)
                    ->with(['planModules' => function ($query) use ($activeSubscription) {
                        $query->where('plan_id', $activeSubscription->plan_id);
                    }])
                    ->first();

                if (! $module) {
                    return false;
                }

                return $module->canUse($activeSubscription);
            }
        );
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
     * Get the cache key for module access.
     */
    public function getCacheKey(string $moduleClass): string
    {
        return "module_access_{$this->id}_{$moduleClass}";
    }

    /**
     * Get the usage count for a specific module.
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
     * @throws \RuntimeException
     * @throws \InvalidArgumentException
     */
    public function recordUsage(string $moduleClass, int $quantity = 1, bool $incremental = true): void
    {
        $activeSubscription = $this->activeSubscription();
        if (! $activeSubscription) {
            return;
        }

        if (version_compare(app()->version(), '11.23', '>=')) {
            defer(function () use ($moduleClass, $quantity, $incremental, $activeSubscription) {
                $this->record($moduleClass, $quantity, $incremental, $activeSubscription);
            });
        } else {
            $this->record($moduleClass, $quantity, $incremental, $activeSubscription);
        }
    }

    public function record(string $moduleClass, int $quantity, bool $incremental, Subscription $activeSubscription): void
    {
        // Load module with a single query including plan modules
        $moduleModel = config('filament-modular-subscriptions.models.module');
        $module = $moduleModel::with(['planModules' => function ($query) use ($activeSubscription) {
            $query->where('plan_id', $activeSubscription->plan_id);
        }])->where('class', $moduleClass)->first();

        if (! $module) {
            throw new \InvalidArgumentException("Module {$moduleClass} not found");
        }

        // Load or create module usage with a single query
        $moduleUsage = $activeSubscription->moduleUsages()
            ->where('module_id', $module->id)
            ->firstOrCreate(
                ['module_id' => $module->id],
                [
                    'usage' => 0,
                    'calculated_at' => now(),
                ]
            );

        // Update usage in a single query
        $newUsage = $incremental ? $moduleUsage->usage + $quantity : $quantity;
        $pricing = $this->calculateModulePricing($activeSubscription, $module, $newUsage);

        $moduleUsage->updateQuietly([
            'usage' => $newUsage,
            'calculated_at' => now(),
            'pricing' => $pricing,
        ]);

        $this->invalidateSubscriptionCache();
    }

    /**
     * Calculate pricing for module usage.
     *
     * @param  mixed  $module
     */
    private function calculateModulePricing(Subscription $subscription, $module, int $usage): float
    {
        if (! $subscription->plan->is_pay_as_you_go) {
            return 0;
        }

        $planModule = $module->planModules->first();

        return $planModule ? $usage * $planModule->price : 0;
    }

    /**
     * Create a new subscription for the model.
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

        $this->invalidateSubscriptionCache();

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

        $this->invalidateSubscriptionCache();

        return $usage;
    }

    /**
     * Calculate total pricing including base plan and module usage.
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
     */
    public function onTrial(): bool
    {
        return $this->activeSubscription() && $this->activeSubscription()->onTrial();
    }

    /**
     * Check if model has a generic trial period.
     */
    public function onGenericTrial(): bool
    {
        return $this->trial_ends_at && $this->trial_ends_at->isFuture();
    }

    /**
     * Get remaining trial days.
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
     */
    public function extendTrial(int $days): void
    {
        if ($this->onTrial()) {
            $subscription = $this->activeSubscription();
            $subscription->trial_ends_at = $subscription->trial_ends_at->addDays($days);
            $subscription->save();
            $this->invalidateSubscriptionCache();
        } elseif ($this->onGenericTrial()) {
            $this->trial_ends_at = $this->trial_ends_at->addDays($days);
            $this->save();
        }
    }

    /**
     * End trial period immediately.
     */
    public function endTrial(): void
    {
        if ($this->onTrial()) {
            $subscription = $this->activeSubscription();
            $subscription->trial_ends_at = now();
            $subscription->save();
            $this->invalidateSubscriptionCache();
        } elseif ($this->onGenericTrial()) {
            $this->trial_ends_at = now();
            $this->save();
        }
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

    public function decrementUsage(string $moduleClass, int $quantity = 1): void
    {
        $activeSubscription = $this->activeSubscription();
        if (! $activeSubscription) {
            return;
        }

        $moduleModel = config('filament-modular-subscriptions.models.module');
        $module = $moduleModel::where('class', $moduleClass)->first();

        if (! $module) {
            return;
        }

        $moduleUsage = $activeSubscription->moduleUsages()
            ->where('module_id', $module->id)
            ->first();

        if ($moduleUsage) {
            $moduleUsage->decrement('usage', $quantity);
        }

        $this->invalidateSubscriptionCache();
    }


    /**
     * Get users who should be notified about subscription changes.
     * This method should be implemented by the tenant model.
     */
    public function getTenantAdminsUsing()
    {
        if (method_exists($this, 'admins')) {
            return $this->admins();
        }
        throw new \Exception('The tenant model must implement getShouldNotifyUsersQuery() or have a admins() relationship');
    }

    /**
     * Notify users about subscription changes
     */
    public function notifySubscriptionChange(string $action, array $additionalData = []): void
    {
        if (version_compare(app()->version(), '11.23', '>=')) {
            defer(function () use ($action, $additionalData) {
                $this->notifyAdminsUsing($action, $additionalData);
            });
        } else {
            $this->notifyAdminsUsing($action, $additionalData);
        }
    }

    public function notifyAdminsUsing(string $action, array $additionalData = []): void
    {
        $users = $this->getTenantAdminsUsing()->get();

        $this->getNotificationUsing(
            __('filament-modular-subscriptions::fms.notifications.subscription.' . $action . '.title'),
            __('filament-modular-subscriptions::fms.notifications.subscription.' . $action . '.body', [
                'tenant' => $this->name
            ])
        )->sendToDatabase($users);
    }

    public function getSuperAdminsQuery(): Builder
    {
        return config('filament-modular-subscriptions.user_model')::query()->role('super_admin');
    }

    public function notifySuperAdmins(string $action, array $additionalData = []): void
    {
        $users = $this->getSuperAdminsQuery()->get();
        $data = array_merge([
            'tenant' => $this->name,
            'date' => now()->format('Y-m-d H:i:s'),
        ], $additionalData);

        $title = __('filament-modular-subscriptions::fms.notifications.admin_message.' . $action . '.title');
        $body = __('filament-modular-subscriptions::fms.notifications.admin_message.' . $action . '.body', $data);

        $this->getNotificationUsing(
            $title,
            $body
        )
            ->icon($this->getNotificationIcon($action))
            ->iconColor($this->getNotificationColor($action))
            ->sendToDatabase($users);
    }

    public function getNotificationUsing($title, $body)
    {
        return Notification::make()
            ->title($title)
            ->body($body);
    }

    protected function getNotificationIcon(string $action): string
    {
        return match ($action) {
            'expired', 'suspended', 'cancelled' => 'heroicon-o-exclamation-triangle',
            'payment_received' => 'heroicon-o-currency-dollar',
            'payment_rejected', 'payment_overdue' => 'heroicon-o-x-circle',
            'invoice_generated' => 'heroicon-o-document-text',
            'invoice_overdue' => 'heroicon-o-clock',
            'subscription_near_expiry' => 'heroicon-o-clock',
            'usage_limit_exceeded' => 'heroicon-o-exclamation-circle',
            default => 'heroicon-o-bell',
        };
    }

    protected function getNotificationColor(string $action): string
    {
        return match ($action) {
            'expired', 'suspended', 'cancelled', 'payment_rejected' => 'danger',
            'payment_received', 'invoice_generated' => 'success',
            'payment_overdue', 'invoice_overdue', 'subscription_near_expiry' => 'warning',
            'usage_limit_exceeded' => 'danger',
            default => 'primary',
        };
    }
}
