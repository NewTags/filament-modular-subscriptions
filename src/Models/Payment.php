<?php

namespace NewTags\FilamentModularSubscriptions\Models;

use NewTags\FilamentModularSubscriptions\Enums\PaymentMethod;
use NewTags\FilamentModularSubscriptions\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    protected $fillable = [
        'invoice_id',
        'amount',
        'payment_method',
        'transaction_id',
        'status',
    ];

    protected $casts = [
        'status' => PaymentStatus::class,
        'metadata' => 'array',
        'amount' => 'decimal:2',
        'payment_method' => PaymentMethod::class,
    ];

    public function getTable()
    {
        return config('filament-modular-subscriptions.tables.payment');
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }
}
