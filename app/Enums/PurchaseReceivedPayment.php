<?php

namespace App\Enums;

use App\Enums\Traits\Commons;

enum PurchaseReceivedPayment: int
{
    use Commons;

    case Cash = 0;
    case Supplier_Account = 5;
}
