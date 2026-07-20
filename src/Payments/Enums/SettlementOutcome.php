<?php

namespace NewTags\FilamentModularSubscriptions\Payments\Enums;

enum SettlementOutcome: string
{
    case ALREADY_PROCESSED = 'already_processed';
    case SETTLED = 'settled';
    case PENDING = 'pending';
    case FAILED = 'failed';
    case MISMATCH = 'mismatch';

    public function isPaid(): bool
    {
        return match ($this) {
            self::SETTLED, self::ALREADY_PROCESSED => true,
            default => false,
        };
    }
}
