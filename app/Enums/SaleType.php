<?php

namespace App\Enums;

use App\Enums\Traits\Commons;

enum SaleType: int
{
    use Commons;

    case Sale = 1;
    case Paused = 2;
    case Sale_Return = 5;
    case Exchange = 10;

    public function label(): string
    {
        return match ($this) {
            self::Sale => 'Sale',
            self::Paused => 'Paused',
            self::Sale_Return => 'Sale Return',
            self::Exchange => 'Exchange',
        };
    }
}
