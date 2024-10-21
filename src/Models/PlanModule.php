<?php

namespace HoceineEl\FilamentModularSubscriptions\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlanModule extends Model
{

    protected $fillable = [
        'plan_id',
        'module_id',
        'limit',
        'price',
        'settings',
    ];

    protected $casts = [
        'limit' => 'integer',
        'price' => 'float',
        'settings' => 'json',
    ];

    public function getTable()
    {
        return config('filament-modular-subscriptions.tables.plan_module');
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(config('filament-modular-subscriptions.models.plan'));
    }

    public function module(): BelongsTo
    {
        return $this->belongsTo(config('filament-modular-subscriptions.models.module'));
    }

    public function getLimitAttribute($value): ?int
    {
        return $value === null ? null : (int) $value;
    }

    public function isUnlimited(): bool
    {
        return $this->limit === null;
    }

    public function getSettingValue(string $key, $default = null)
    {
        return data_get($this->settings, $key, $default);
    }

    public function setSettingValue(string $key, $value): void
    {
        $settings = $this->settings ?? [];
        data_set($settings, $key, $value);
        $this->settings = $settings;
    }
}
