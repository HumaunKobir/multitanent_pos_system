<?php

namespace App\Enums;

enum PromotionType: string
{
    case Percent = 'percent';
    case Flat = 'flat';
    case FixedPrice = 'fixed_price';
    case BuyXGetY = 'buy_x_get_y';
    case Bundle = 'bundle';

    public function label(): string
    {
        return match ($this) {
            self::Percent => 'Percent (%)',
            self::Flat => 'Flat (৳)',
            self::FixedPrice => 'Fixed Price',
            self::BuyXGetY => 'Buy X Get Y',
            self::Bundle => 'Bundle',
        };
    }
}
