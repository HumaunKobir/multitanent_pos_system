<?php

namespace App\Enums;

enum PromotionScope: string
{
    case Category = 'category';
    case Brand = 'brand';
    case Product = 'product';

    public function label(): string
    {
        return match ($this) {
            self::Category => 'Category',
            self::Brand => 'Brand',
            self::Product => 'Product',
        };
    }
}
