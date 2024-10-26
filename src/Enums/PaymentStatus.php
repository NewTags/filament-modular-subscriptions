<?php

namespace HoceineEl\FilamentModularSubscriptions\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum PaymentStatus: string implements HasColor, HasIcon, HasLabel
{
    case PAID = 'paid';
    case UNPAID = 'unpaid';
    case PARTIALLY_PAID = 'partially_paid';
    case PENDING = 'pending';
    case CANCELLED = 'cancelled';

    public function getLabel(): string
    {
        return match ($this) {
            self::PAID => __('filament-modular-subscriptions::fms.status.paid'),
            self::UNPAID => __('filament-modular-subscriptions::fms.status.unpaid'),
            self::PARTIALLY_PAID => __('filament-modular-subscriptions::fms.status.partially_paid'),
            self::PENDING => __('filament-modular-subscriptions::fms.status.pending'),
        };
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::PAID => 'heroicon-o-check-circle',
            self::UNPAID => 'heroicon-o-x-circle',
            self::PARTIALLY_PAID => 'heroicon-o-clock',
            self::PENDING => 'heroicon-o-clock',
            self::CANCELLED => 'heroicon-o-x-circle',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::PAID => 'success',
            self::UNPAID => 'danger',
            self::PARTIALLY_PAID => 'warning',
            self::PENDING => 'warning',
            self::CANCELLED => 'danger',
        };
    }
}
