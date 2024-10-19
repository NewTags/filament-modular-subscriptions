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
            self::DAY => __('Day'),
            self::WEEK => __('Week'),
            self::MONTH => __('Month'),
            self::YEAR => __('Year'),
        };
    }
}
