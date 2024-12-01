<?php

namespace HoceineEl\FilamentModularSubscriptions\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubscriptionLog extends Model
{
    protected $fillable = [
        'subscription_id',
        'event',
        'description',
        'old_status',
        'new_status',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'json',
    ];

    public function getTable()
    {
        return config('filament-modular-subscriptions.tables.subscription_log');
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(config('filament-modular-subscriptions.models.subscription'));
    }
}
