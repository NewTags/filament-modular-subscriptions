<?php

namespace HoceineEl\FilamentModularSubscriptions\Models;

use HoceineEl\FilamentModularSubscriptions\Enums\Interval;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
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

    public function getTransNameAttribute($value)
    {
        $locale = app()->getLocale();
        $names = json_decode($value, true);

        return $names[$locale] ?? $names['en'] ?? '';
    }

    public function modules(): HasMany
    {
        return $this->hasMany(config('filament-modular-subscriptions.models.plan_module'));
    }

    public function modulePrice(Model | string $module): float
    {
        $moduleModel = config('filament-modular-subscriptions.models.module');
        $module = $module instanceof $moduleModel ? $module : $moduleModel::where('class', $module)->first();

        if (! $module) {
            return -1;
        }

        $modulePrice = $this->modules()->where('module_id', $module->id)->first();

        if (! $modulePrice) {
            return -1;
        }

        return $modulePrice->pivot->price;
    }
}
