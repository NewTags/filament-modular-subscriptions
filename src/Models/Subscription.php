<?php

namespace HoceineEl\FilamentModularSubscriptions\Models;

use HoceineEl\FilamentModularSubscriptions\Enums\SubscriptionStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

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
}
