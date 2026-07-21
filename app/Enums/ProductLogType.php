<?php

namespace App\Enums;

use App\Enums\Traits\Commons;

enum ProductLogType: int
{
    use Commons;

    case Purchase = 1;
    case Sale = 5;
    case Sale_Return = 10;
    case Damage = 11;
    case Purchase_Return = 12;
    case InitialStock = 13;
    case Exchange = 14;
    case Distribution_Out = 15;
    case Distribution_In = 16;
    case Adjustment_In = 17;
    case Adjustment_Out = 18;
}
