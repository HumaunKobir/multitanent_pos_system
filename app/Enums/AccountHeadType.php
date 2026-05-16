<?php

namespace App\Enums;

use App\Enums\Traits\Commons;

enum AccountHeadType: int
{
    use Commons;

    case Out = 0;
    case In = 1;
}
