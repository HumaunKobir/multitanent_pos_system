<?php

namespace App\Enums;

use App\Enums\Traits\Commons;

enum PurchaseType: int
{
    use Commons;

    case Purchase = 1;
    case Damage = 2;
    case Purchase_Return = 5;
    case InitialStock = 6;
    case OpeningBalance = 7;

    public function label(): string
    {
        return match ($this) {
            self::Purchase => 'Purchase',
            self::Damage => 'Damage',
            self::Purchase_Return => 'Purchase Return',
            self::InitialStock => 'Initial Stock',
            self::OpeningBalance => 'Opening Balance',
        };
    }
}
