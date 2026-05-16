<?php

namespace App\Enums;

use App\Enums\Traits\Commons;

enum SaleType: int
{
    use Commons;

    case Sale = 1;
    case Sale_Return = 5;
    case Exchange = 10;
}
