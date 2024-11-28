<?php

namespace HoceineEl\FilamentModularSubscriptions\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum SubscriptionStatus: string implements HasColor, HasIcon, HasLabel
{
    case ACTIVE = 'active';
    case CANCELLED = 'cancelled';
    case EXPIRED = 'expired';
    case PENDING = 'pending';
    case PENDING_PAYMENT = 'pending_payment';

    public function getLabel(): string
    {
        return match ($this) {
            self::ACTIVE => __('filament-modular-subscriptions::fms.status.active'),
            self::CANCELLED => __('filament-modular-subscriptions::fms.status.cancelled'),
            self::EXPIRED => __('filament-modular-subscriptions::fms.status.expired'),
            self::PENDING => __('filament-modular-subscriptions::fms.status.pending'),
            self::PENDING_PAYMENT => __('filament-modular-subscriptions::fms.status.pending_payment'),
        };
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::ACTIVE => 'heroicon-o-check-circle',
            self::CANCELLED => 'heroicon-o-x-circle',
            self::EXPIRED => 'heroicon-o-x-circle',
            self::PENDING => 'heroicon-o-clock',
            self::PENDING_PAYMENT => 'heroicon-o-clock',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::ACTIVE => 'success',
            self::CANCELLED => 'danger',
            self::EXPIRED => 'warning',
            self::PENDING => 'info',
            self::PENDING_PAYMENT => 'info',
        };
    }
}
