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
            self::DAY => __('filament-modular-subscriptions::fms.interval.day'),
            self::WEEK => __('filament-modular-subscriptions::fms.interval.week'),
            self::MONTH => __('filament-modular-subscriptions::fms.interval.month'),
            self::YEAR => __('filament-modular-subscriptions::fms.interval.year'),
        };
    }

    public function days(): int
    {
        return match ($this) {
            self::DAY => 1,
            self::WEEK => 7,
            self::MONTH => 30,
            self::YEAR => 365,
        };
    }
}
