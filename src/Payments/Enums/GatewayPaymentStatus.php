<?php

namespace NewTags\FilamentModularSubscriptions\Payments\Enums;

/**
 * Gateway-agnostic charge status. Every driver maps its provider-specific
 * statuses onto this enum so checkout and settlement logic never needs to
 * know provider vocabulary.
 */
enum GatewayPaymentStatus: string
{
    case PENDING = 'pending';
    case PAID = 'paid';
    case FAILED = 'failed';
    case EXPIRED = 'expired';
    case CANCELLED = 'cancelled';
    case UNKNOWN = 'unknown';

    public function isPaid(): bool
    {
        return $this === self::PAID;
    }

    public function isTerminal(): bool
    {
        return match ($this) {
            self::PAID, self::FAILED, self::EXPIRED, self::CANCELLED => true,
            self::PENDING, self::UNKNOWN => false,
        };
    }
}
