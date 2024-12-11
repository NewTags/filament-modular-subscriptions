<?php

namespace HoceineEl\FilamentModularSubscriptions\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ModuleUsage extends Model
{
    protected $fillable = ['subscription_id', 'module_id', 'usage', 'pricing', 'calculated_at'];

    protected $casts = [
        'calculated_at' => 'datetime',
        'usage' => 'integer',
        'pricing' => 'float',
    ];

    public function getTable()
    {
        return config('filament-modular-subscriptions.tables.usage');
    }

    public function subscription(): BelongsTo
    {
        $subscriptionModel = config('filament-modular-subscriptions.models.subscription');

        return $this->belongsTo($subscriptionModel);
    }

    public function module(): BelongsTo
    {
        $moduleModel = config('filament-modular-subscriptions.models.module');
        return $this->belongsTo($moduleModel);
    }
}
