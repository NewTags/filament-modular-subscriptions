<?php

namespace HoceineEl\FilamentModularSubscriptions\Enums;

use Filament\Support\Contracts\HasLabel;

enum Interval: string implements HasLabel
{
    case DAY = 'day';
    case WEEK = 'week';
    case MONTH = 'month';
    case YEAR = 'year';

    public function getLabel(): string
    {
        return match ($this) {
            self::DAY => __('filament-modular-subscriptions::Day'),
            self::WEEK => __('filament-modular-subscriptions::Week'),
            self::MONTH => __('filament-modular-subscriptions::Month'),
            self::YEAR => __('filament-modular-subscriptions::Year'),
        };
    }
}
