<?php

namespace NewTags\FilamentModularSubscriptions\Models;

use NewTags\FilamentModularSubscriptions\Enums\InvoiceStatus;
use NewTags\FilamentModularSubscriptions\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Invoice extends Model
{
    protected $fillable = [
        'subscription_id',
        'tenant_id',
        'amount',
        'subtotal',
        'tax',
        'status',
        'due_date',
        'paid_at',

    ];

    protected $casts = [
        'due_date' => 'datetime',
        'paid_at' => 'datetime',
        'status' => InvoiceStatus::class,
    ];

    public function getTable()
    {
        return config('filament-modular-subscriptions.tables.invoice');
    }

    public function remainingAmount(): Attribute
    {
        return new Attribute(
            get: function () {
                $totalPayments = $this->payments()
                    ->where('status', PaymentStatus::PAID)
                    ->sum('amount');

                $totalAmount = $this->amount;
                $remaining = $totalAmount - $totalPayments;

                return round($remaining, 2);
            }
        );
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

    public function notPaid(): bool
    {
        if ($this->status === InvoiceStatus::CANCELLED || $this->status === InvoiceStatus::REFUNDED) {
            return false;
        }

        $totalPayments = $this->payments()->where('status', PaymentStatus::PAID)->sum('amount');

        return $this->status === InvoiceStatus::UNPAID || $this->status === InvoiceStatus::PARTIALLY_PAID || $totalPayments < $this->amount;
    }

    public function paid(): bool
    {
        return $this->status === InvoiceStatus::PAID;
    }


    public function getTitleAttribute()
    {
        $subscriber = $this->subscription ? $this->subscription->subscriber : null;
        $tenantAttribute = config('filament-modular-subscriptions.tenant_attribute');
        $subscriberName = $subscriber ? $subscriber->{$tenantAttribute} : __('N/A');

        return __('filament-modular-subscriptions::fms.invoice.invoice_title', [
            'subscriber' => $subscriberName,
            'id' => $this->id,
            'date' => $this->created_at->translatedFormat('Y-m-d'),
        ]);
    }
}
