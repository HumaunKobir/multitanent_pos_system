<?php

namespace App\Enums;

enum CoinExpiryUnit: string
{
    case Day = 'day';
    case Week = 'week';
    case Month = 'month';
    case Year = 'year';

    public function label(): string
    {
        return match ($this) {
            self::Day => 'Day(s)',
            self::Week => 'Week(s)',
            self::Month => 'Month(s)',
            self::Year => 'Year(s)',
        };
    }

    public function labelSingular(): string
    {
        return match ($this) {
            self::Day => 'day',
            self::Week => 'week',
            self::Month => 'month',
            self::Year => 'year',
        };
    }
}
