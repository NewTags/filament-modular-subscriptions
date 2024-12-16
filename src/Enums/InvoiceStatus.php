<?php

namespace NewTags\FilamentModularSubscriptions\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum InvoiceStatus: string implements HasColor, HasLabel
{
    case PAID = 'paid';
    case PARTIALLY_PAID = 'partially_paid';
    case UNPAID = 'unpaid';
    case OVERDUE = 'overdue';
    case CANCELLED = 'cancelled';
    case REFUNDED = 'refunded';

    public function getLabel(): string
    {
        return match ($this) {
            self::PAID => __('filament-modular-subscriptions::fms.invoice_status.paid'),
            self::PARTIALLY_PAID => __('filament-modular-subscriptions::fms.invoice_status.partially_paid'),
            self::UNPAID => __('filament-modular-subscriptions::fms.invoice_status.unpaid'),
            self::OVERDUE => __('filament-modular-subscriptions::fms.invoice_status.overdue'),
            self::CANCELLED => __('filament-modular-subscriptions::fms.invoice_status.cancelled'),
            self::REFUNDED => __('filament-modular-subscriptions::fms.invoice_status.refunded'),
        };
    }

    public function getColor(): string | array | null
    {
        return match ($this) {
            self::PAID => 'success',
            self::PARTIALLY_PAID => 'warning',
            self::UNPAID => 'danger',
            self::OVERDUE => 'danger',
            self::CANCELLED => 'gray',
            self::REFUNDED => 'info',
        };
    }
}
