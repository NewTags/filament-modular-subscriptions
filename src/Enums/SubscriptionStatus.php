<?php

namespace NewTags\FilamentModularSubscriptions\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum SubscriptionStatus: string implements HasColor, HasIcon, HasLabel
{
    case ACTIVE = 'active';
    case CANCELLED = 'cancelled';
    case EXPIRED = 'expired';
    case ON_HOLD = 'on_hold';
    case PENDING_PAYMENT = 'pending_payment';

    public function getLabel(): string
    {
        return match ($this) {
            self::ACTIVE => __('filament-modular-subscriptions::fms.statuses.active'),
            self::CANCELLED => __('filament-modular-subscriptions::fms.statuses.cancelled'),
            self::EXPIRED => __('filament-modular-subscriptions::fms.statuses.expired'),
            self::ON_HOLD => __('filament-modular-subscriptions::fms.statuses.on_hold'),
            self::PENDING_PAYMENT => __('filament-modular-subscriptions::fms.statuses.pending_payment'),
        };
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::ACTIVE => 'heroicon-o-check-circle',
            self::CANCELLED => 'heroicon-o-x-circle',
            self::EXPIRED => 'heroicon-o-x-circle',
            self::ON_HOLD => 'heroicon-o-clock',
            self::PENDING_PAYMENT => 'heroicon-o-clock',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::ACTIVE => 'success',
            self::CANCELLED => 'danger',
            self::EXPIRED => 'warning',
            self::ON_HOLD => 'info',
            self::PENDING_PAYMENT => 'info',
        };
    }
}
