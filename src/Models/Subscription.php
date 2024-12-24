<?php

namespace NewTags\FilamentModularSubscriptions\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Casts\Attribute;
use NewTags\FilamentModularSubscriptions\Enums\SubscriptionStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Subscription extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'plan_id',
        'subscribable_id',
        'subscribable_type',
        'starts_at',
        'ends_at',
        'trial_ends_at',
        'status',
    ];

    public function getTable()
    {
        return config('filament-modular-subscriptions.tables.subscription');
    }

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'trial_ends_at' => 'datetime',
        'status' => SubscriptionStatus::class,
    ];

    public function plan(): BelongsTo
    {
        $planModel = config('filament-modular-subscriptions.models.plan');

        return $this->belongsTo($planModel);
    }

    public function subscriber(): BelongsTo
    {

        $tenantModel = config('filament-modular-subscriptions.tenant_model');
        if (! $tenantModel) {
            throw new \Exception('Tenant model not set in config/filament-modular-subscriptions.php');
        }

        return $this->belongsTo($tenantModel, 'subscribable_id');
    }

    public function subscribable(): MorphTo
    {
        return $this->morphTo();
    }

    public function moduleUsages(): HasMany
    {
        $moduleUsageModel = config('filament-modular-subscriptions.models.usage');

        return $this->hasMany($moduleUsageModel);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function onTrial(): bool
    {
        return $this->trial_ends_at && $this->trial_ends_at->isFuture();
    }

    public function hasExpiredTrial(): bool
    {
        return $this->trial_ends_at && $this->trial_ends_at->isPast();
    }

    public function renew(?int $days = null): bool
    {
        return $this->subscriber->renew($days);
    }

    /**
     * Get the number of days left in the current subscription period.
     */
    public function daysLeft(): ?float
    {
        if (! $this->ends_at) {
            return null;
        }

        return round(now()->diffInDays($this->ends_at, false), 1);
    }

    /**
     * Get the number of days left including grace period.
     */
    public function daysLeftWithGrace(): ?float
    {
        $gracePeriodEndDate = $this->getGracePeriodEndDate($this);

        return $gracePeriodEndDate
            ? round(now()->diffInDays($gracePeriodEndDate, false), 1)
            : null;
    }


    public function isExpired(): bool
    {
        if ($this->status === SubscriptionStatus::EXPIRED) {
            return true;
        }

        if (!$this->ends_at) {
            return false;
        }

        $gracePeriodEndDate = $this->getGracePeriodEndDate();
        
        return $gracePeriodEndDate && now()->isAfter($gracePeriodEndDate);
    }


    public function IsPayAsYouGo() : Attribute
    {
        return Attribute::make(
            get: fn() => $this->plan->is_pay_as_you_go,
        );
    }

    public function isInGracePeriod(): bool
    {
        $now = now();
        $endsAt = $this->ends_at;
        $gracePeriodEndDate = $this->getGracePeriodEndDate();

        return $endsAt && $gracePeriodEndDate &&
            $now->isAfter($endsAt) &&
            $now->isBefore($gracePeriodEndDate);
    }

    /**
     * Calculate the end date including grace period.
     */
    private function getGracePeriodEndDate(?Subscription $subscription = null): ?Carbon
    {
        $subscription = $subscription ?? $this;
        if (! $subscription->ends_at) {
            return null;
        }

        $gracePeriodDays = $subscription->plan?->period_grace ?? 0;

        return $subscription->ends_at->copy()->addDays($gracePeriodDays);
    }
}
