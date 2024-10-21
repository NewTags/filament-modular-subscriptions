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


    public function getLabel(): string
    {
        return match ($this) {
            self::PAID => __('filament-modular-subscriptions::modular-subscriptions.status.paid'),
            self::UNPAID => __('filament-modular-subscriptions::modular-subscriptions.status.unpaid'),
            self::PARTIALLY_PAID => __('filament-modular-subscriptions::modular-subscriptions.status.partially_paid'),
        };
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::PAID => 'heroicon-o-check-circle',
            self::UNPAID => 'heroicon-o-x-circle',
            self::PARTIALLY_PAID => 'heroicon-o-clock',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::PAID => 'success',
            self::UNPAID => 'danger',
            self::PARTIALLY_PAID => 'warning',
        };
    }
}
