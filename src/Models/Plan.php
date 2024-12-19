<?php

namespace NewTags\FilamentModularSubscriptions\Models;

use NewTags\FilamentModularSubscriptions\Enums\Interval;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
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
        'is_pay_as_you_go',
        'fixed_invoice_day',
        'is_trial_plan',
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
        'is_pay_as_you_go' => 'boolean',
        'fixed_invoice_day' => 'integer',
        'is_trial_plan' => 'boolean',
    ];

    public function getTable()
    {
        return config('filament-modular-subscriptions.tables.plan');
    }

    public function subscriptions(): HasMany
    {
        $subscriptionModel = config('filament-modular-subscriptions.models.subscription');

        return $this->hasMany($subscriptionModel);
    }

    public function TransName(): Attribute
    {
        return Attribute::make(
            get: fn() => $this->name[app()->getLocale()] ?? $this->name['en'] ?? '',
        );
    }

    public function planModules(): HasMany
    {
        return $this->hasMany(PlanModule::class);
    }

    public function Period(): Attribute
    {
        return Attribute::make(
            get: fn() => $this->invoice_interval->days() * $this->invoice_period,
        );
    }

    public function PeriodTrial(): Attribute
    {
        return Attribute::make(
            get: fn() => $this->trial_interval->days() * $this->trial_period,
        );
    }

    public function PeriodGrace(): Attribute
    {
        return Attribute::make(
            get: fn() => $this->grace_interval->days() * $this->grace_period,
        );
    }

    public function modules(): BelongsToMany
    {
        $moduleModel = config('filament-modular-subscriptions.models.module');

        return $this->belongsToMany($moduleModel, config('filament-modular-subscriptions.tables.plan_module'))
            ->withPivot(['limit', 'price', 'settings']);
    }

    public function modulePrice(Model | string $module): float
    {
        $moduleModel = config('filament-modular-subscriptions.models.module');
        $module = $module instanceof $moduleModel ? $module : $moduleModel::where('class', $module)->first();

        if (! $module) {
            return -1;
        }

        $planModule = $this->planModules()->where('module_id', $module->id)->first();

        if (! $planModule) {
            return -1;
        }

        return $planModule->price;
    }

    public function moduleLimit(Model | string $module): int
    {
        $moduleModel = config('filament-modular-subscriptions.models.module');
        $module = $module instanceof $moduleModel ? $module : $moduleModel::where('class', $module)->first();

        return $this->planModules()->where('module_id', $module->id)->first()->limit;
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function isPayAsYouGo(): bool
    {
        return $this->is_pay_as_you_go;
    }

    public function scopePayAsYouGo(Builder $query): Builder
    {
        return $query->where('is_pay_as_you_go', true);
    }

    public function isTrialPlan(): bool
    {
        return $this->is_trial_plan;
    }
}
