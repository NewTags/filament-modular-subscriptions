<?php

namespace NewTags\FilamentModularSubscriptions\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum PaymentCheckoutStatus: string implements HasColor, HasIcon, HasLabel
{
    case PENDING = 'pending';
    case INITIATED = 'initiated';
    case PAID = 'paid';
    case FAILED = 'failed';
    case EXPIRED = 'expired';
    case CANCELLED = 'cancelled';
    case ERROR = 'error';

    public function getLabel(): string
    {
        return match ($this) {
            self::PENDING => __('filament-modular-subscriptions::fms.payments.checkout_statuses.pending'),
            self::INITIATED => __('filament-modular-subscriptions::fms.payments.checkout_statuses.initiated'),
            self::PAID => __('filament-modular-subscriptions::fms.payments.checkout_statuses.paid'),
            self::FAILED => __('filament-modular-subscriptions::fms.payments.checkout_statuses.failed'),
            self::EXPIRED => __('filament-modular-subscriptions::fms.payments.checkout_statuses.expired'),
            self::CANCELLED => __('filament-modular-subscriptions::fms.payments.checkout_statuses.cancelled'),
            self::ERROR => __('filament-modular-subscriptions::fms.payments.checkout_statuses.error'),
        };
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::PENDING, self::INITIATED => 'heroicon-o-clock',
            self::PAID => 'heroicon-o-check-circle',
            self::FAILED, self::ERROR => 'heroicon-o-x-circle',
            self::EXPIRED => 'heroicon-o-clock',
            self::CANCELLED => 'heroicon-o-no-symbol',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::PENDING, self::INITIATED => 'warning',
            self::PAID => 'success',
            self::FAILED, self::ERROR => 'danger',
            self::EXPIRED, self::CANCELLED => 'gray',
        };
    }
}
