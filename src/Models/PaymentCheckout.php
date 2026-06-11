<?php

namespace NewTags\FilamentModularSubscriptions\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use NewTags\FilamentModularSubscriptions\Enums\PaymentCheckoutStatus;

/**
 * A hosted-checkout attempt against a gateway. Checkouts track redirects and
 * charge ids; actual money is only ever represented by a Payment row created
 * at settlement time.
 */
class PaymentCheckout extends Model
{
    protected $fillable = [
        'uuid',
        'invoice_id',
        'gateway',
        'charge_id',
        'amount',
        'currency',
        'status',
        'checkout_url',
        'return_url',
        'source',
        'payment_id',
        'created_by',
        'metadata',
        'gateway_response',
        'expires_at',
        'processed_at',
    ];

    protected $casts = [
        'status' => PaymentCheckoutStatus::class,
        'amount' => 'decimal:2',
        'metadata' => 'array',
        'gateway_response' => 'array',
        'expires_at' => 'datetime',
        'processed_at' => 'datetime',
    ];

    public function getTable()
    {
        return config('filament-modular-subscriptions.tables.payment_checkout', 'fms_payment_checkouts');
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(config('filament-modular-subscriptions.models.invoice'));
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(config('filament-modular-subscriptions.models.payment'));
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(config('auth.providers.users.model') ?? User::class, 'created_by');
    }

    public function isProcessed(): bool
    {
        return $this->processed_at !== null;
    }

    /**
     * Fresh, unprocessed checkouts whose hosted page is still valid and can be
     * reused instead of creating a duplicate charge.
     */
    public function scopeReusableFor(Builder $query, int $invoiceId, string $gateway, float $amount): Builder
    {
        return $query
            ->where('invoice_id', $invoiceId)
            ->where('gateway', $gateway)
            ->where('status', PaymentCheckoutStatus::INITIATED)
            ->whereNull('processed_at')
            ->where('amount', number_format($amount, 2, '.', ''))
            ->where('created_at', '>=', now()->subMinutes(20))
            ->whereNotNull('checkout_url');
    }
}
