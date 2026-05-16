<?php

namespace App\Enums;

use App\Enums\Traits\Commons;

enum ProductType: int
{
    use Commons;

    case RegularSale = 1;
    case WholeSale = 2;
}
