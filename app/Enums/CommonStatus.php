<?php

namespace App\Enums;

use App\Enums\Traits\Commons;

enum CommonStatus: int
{
    use Commons;

    case Pending = 0;
    case Active = 1;
    case InActive = 2;
}
