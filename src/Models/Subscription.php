<?php

namespace HoceineEl\LaravelModularSubscriptions\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\Config;
use HoceineEl\LaravelModularSubscriptions\Enums\SubscriptionStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class Subscription extends Model
{
    use HasFactory, SoftDeletes;
    protected $fillable = [
        'plan_id',
        'subscribable_id',
        'subscribable_type',
        'starts_at',
        'ends_at',
        'trial_ends_at',
        'status'
    ];

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

    public function subscribable(): MorphTo
    {
        return $this->morphTo();
    }

    public function moduleUsages(): HasMany
    {
        $moduleUsageModel = config('filament-modular-subscriptions.models.usage');
        return $this->hasMany($moduleUsageModel);
    }

    public function onTrial(): bool
    {
        return $this->trial_ends_at && $this->trial_ends_at->isFuture();
    }

    public function hasExpiredTrial(): bool
    {
        return $this->trial_ends_at && $this->trial_ends_at->isPast();
    }
}
