<?php

namespace App\Enums;

use App\Enums\Traits\Commons;

enum CustomerDueAlertStatus: int
{
    use Commons;

    case Unpaid = 1;
    case Paid = 2;
    case DateChanged = 3;
}
