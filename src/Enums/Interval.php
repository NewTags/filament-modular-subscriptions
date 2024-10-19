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
            self::DAY => __('filament-modular-subscriptions::modular-subscriptions.interval.day'),
            self::WEEK => __('filament-modular-subscriptions::modular-subscriptions.interval.week'),
            self::MONTH => __('filament-modular-subscriptions::modular-subscriptions.interval.month'),
            self::YEAR => __('filament-modular-subscriptions::modular-subscriptions.interval.year'),
        };
    }
}
