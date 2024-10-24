<?php

namespace HoceineEl\FilamentModularSubscriptions\Models;

use HoceineEl\FilamentModularSubscriptions\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Invoice extends Model
{
    protected $fillable = [
        'subscription_id',
        'tenant_id',
        'amount',
        'status',
        'due_date',
        'paid_at',

    ];

    protected $casts = [
        'due_date' => 'datetime',
        'paid_at' => 'datetime',
        'status' => PaymentStatus::class,
    ];

    public function getTable()
    {
        return config('filament-modular-subscriptions.tables.invoice');
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(config('filament-modular-subscriptions.tenant_model'));
    }

    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function getTitleAttribute()
    {
        $subscriber = $this->subscription ? $this->subscription->subscriber : null;
        $tenantAttribute = config('filament-modular-subscriptions.tenant_attribute');
        $subscriberName = $subscriber ? $subscriber->{$tenantAttribute} : __('N/A');

        return __('filament-modular-subscriptions::modular-subscriptions.invoice.invoice_title', [
            'subscriber' => $subscriberName,
            'id' => $this->id,
            'date' => $this->created_at->translatedFormat('Y-m-d')
        ]);
    }
}
