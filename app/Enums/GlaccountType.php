<?php

namespace App\Enums;

use App\Enums\Traits\Commons;

enum GlaccountType: int
{
    use Commons;

    case Assets = 0;
    case Liabilities = 1;
    case Income = 2;
    case Expense = 4;
}
