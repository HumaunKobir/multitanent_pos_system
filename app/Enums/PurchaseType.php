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
}
