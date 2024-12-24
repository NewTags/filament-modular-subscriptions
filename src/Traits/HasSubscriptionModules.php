<?php

namespace NewTags\FilamentModularSubscriptions\Traits;


use Illuminate\Database\Eloquent\Builder;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Cache;
use NewTags\FilamentModularSubscriptions\Enums\InvoiceStatus;
use NewTags\FilamentModularSubscriptions\Enums\SubscriptionStatus;
use NewTags\FilamentModularSubscriptions\Models\Subscription;
use NewTags\FilamentModularSubscriptions\Modules\BaseModule;

trait HasSubscriptionModules
{
    private const MODULE_ACCESS_CACHE_TTL = 1800; // 30 minutes in seconds

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
                $subscription = $this->currentSubscription();
                if (! $subscription) {
                    return false;
                }

                // Check if subscription is on hold or pending payment
                if ($subscription->status === SubscriptionStatus::ON_HOLD || $subscription->status === SubscriptionStatus::PENDING_PAYMENT) {
                    if ($subscription->ends_at->isPast()) {
                        $latestInvoice = $subscription->invoices()->whereIn('status', [InvoiceStatus::UNPAID, InvoiceStatus::PARTIALLY_PAID])->latest()->first();
                        if ($latestInvoice && $latestInvoice->due_date->isPast()) {
                            return false;
                        } elseif ($latestInvoice && $latestInvoice->due_date->isFuture()) {
                            return true;
                        }
                    }
                }

                $moduleModel = config('filament-modular-subscriptions.models.module');
                /** @var \NewTags\FilamentModularSubscriptions\Models\Module $module */
                $module = $moduleModel::where('class', $moduleClass)
                    ->with(['planModules' => function ($query) use ($subscription) {
                        $query->where('plan_id', $subscription->plan_id);
                    }])
                    ->first();

                if (! $module) {
                    return false;
                }

                return $module->canUse($subscription);
            }
        );
    }

    public function currentSubscription(): ?Subscription
    {

        return $this->subscription()
            ->with(['plan', 'moduleUsages.module']) // Eager load relationships
            ->whereDate('starts_at', '<=', now())
            ->where(function ($query) {
                $this->loadMissing('plan');
                $query
                    ->whereDate('ends_at', '>', now())
                    ->orWhereDate('ends_at', '>=', now()->subDays(
                        $this->plan?->period_grace ?? 0
                    ));
            })
            ->whereIn('status', [SubscriptionStatus::ACTIVE, SubscriptionStatus::ON_HOLD, SubscriptionStatus::PENDING_PAYMENT])
            ->first();
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
        $activeSubscription = $this->currentSubscription();
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
        $activeSubscription = $this->currentSubscription();
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


    public function clearModuleCache(BaseModule $module): void
    {
        Cache::forget($this->getCacheKey($module::class));
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

        $this->clearFmsCache();
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

        $this->clearFmsCache();

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

        $this->clearFmsCache();
    }
    public function remainingUsage(string $moduleClass): int
    {
        $activeSubscription = $this->activeSubscription();
        if (! $activeSubscription) {
            return 0;
        }

        $moduleModel = config('filament-modular-subscriptions.models.module');
        $module = $moduleModel::where('class', $moduleClass)->first();

        if (! $module) {
            return 0;
        }

        $moduleLimit = $activeSubscription->plan->moduleLimit($moduleClass);
        
        // Return a large number if module limit is null or 0
        if ($moduleLimit === null || $moduleLimit === 0) {
            return PHP_INT_MAX;
        }

        $remaining = $moduleLimit - $module->calculateUsage($activeSubscription);

        return $remaining > 0 ? $remaining : 0;
    }
    public function createSubscriptionModulesUsages(): void
    {
        $subscription = $this->currentSubscription();
        if (! $subscription) {
            return;
        }
        $subscription->loadMissing('plan.modules');
        $subscription->plan->modules->each(function ($module) use ($subscription) {
            $plan = $subscription->plan;
            if (!$plan->is_pay_as_you_go && $plan->moduleLimit($module) > 0) {
                $subscription->moduleUsages()->firstOrCreate(
                    ['module_id' => $module->id],
                    [
                        'usage' => 0,
                        'calculated_at' => now(),
                    ]
                );
            }
        });
    }
}
