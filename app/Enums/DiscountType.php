<?php

namespace App\Enums;

enum DiscountType: string
{
    case Flat = 'flat';
    case Percent = 'percent';

    public function label(): string
    {
        return match ($this) {
            self::Flat => 'Flat (৳)',
            self::Percent => 'Percent (%)',
        };
    }
}
