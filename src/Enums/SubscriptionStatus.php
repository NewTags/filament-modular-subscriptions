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

    public function getLabel(): string
    {
        return match ($this) {
            self::ACTIVE => __('filament-modular-subscriptions::modular-subscriptions.status.active'),
            self::CANCELLED => __('filament-modular-subscriptions::modular-subscriptions.status.cancelled'),
            self::EXPIRED => __('filament-modular-subscriptions::modular-subscriptions.status.expired'),
            self::PENDING => __('filament-modular-subscriptions::modular-subscriptions.status.pending'),
        };
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::ACTIVE => 'heroicon-o-check-circle',
            self::CANCELLED => 'heroicon-o-x-circle',
            self::EXPIRED => 'heroicon-o-clock',
            self::PENDING => 'heroicon-o-clock',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::ACTIVE => 'success',
            self::CANCELLED => 'danger',
            self::EXPIRED => 'warning',
            self::PENDING => 'info',
        };
    }
}
