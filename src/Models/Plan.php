<?php

namespace HoceineEl\LaravelModularSubscriptions\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use HoceineEl\LaravelModularSubscriptions\Enums\Interval;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class Plan extends Model
{
    use HasFactory, SoftDeletes;



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
}
