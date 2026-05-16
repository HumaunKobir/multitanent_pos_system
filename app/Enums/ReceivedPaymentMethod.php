<?php

namespace App\Enums;

use App\Enums\Traits\Commons;

enum ReceivedPaymentMethod: int
{
    use Commons;

    case Cash = 0;
    case Customer_Account = 5;
}
