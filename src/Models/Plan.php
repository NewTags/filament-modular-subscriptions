<?php

namespace HoceineEl\FilamentModularSubscriptions\Models;

use HoceineEl\FilamentModularSubscriptions\Enums\Interval;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Plan extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'is_active',
        'price',
        'currency',
        'trial_period',
        'trial_interval',
        'invoice_period',
        'invoice_interval',
        'grace_period',
        'grace_interval',
        'sort_order',
    ];

    protected $casts = [
        'name' => 'json',
        'description' => 'json',
        'is_active' => 'boolean',
        'price' => 'decimal:2',
        'trial_period' => 'integer',
        'invoice_period' => 'integer',
        'grace_period' => 'integer',
        'sort_order' => 'integer',
        'trial_interval' => Interval::class,
        'invoice_interval' => Interval::class,
        'grace_interval' => Interval::class,
    ];

    public function subscriptions(): HasMany
    {
        $subscriptionModel = config('filament-modular-subscriptions.models.subscription');
        return $this->hasMany($subscriptionModel);
    }

    public function getTransNameAttribute()
    {
        $locale = app()->getLocale();
        $names = $this->name;
        return $names[$locale] ?? $names['en'] ?? '';
    }

    public function planModules(): HasMany
    {
        return $this->hasMany(PlanModule::class);
    }

    public function modules(): BelongsToMany
    {
        return $this->belongsToMany(config('filament-modular-subscriptions.models.module'), 'plan_modules')
            ->using(PlanModule::class)
            ->withPivot(['limit', 'price', 'settings']);
    }

    public function modulePrice(Model | string $module): float
    {
        $moduleModel = config('filament-modular-subscriptions.models.module');
        $module = $module instanceof $moduleModel ? $module : $moduleModel::where('class', $module)->first();

        if (!$module) {
            return -1;
        }

        $planModule = $this->planModules()->where('module_id', $module->id)->first();

        if (!$planModule) {
            return -1;
        }

        return $planModule->price;
    }
}
