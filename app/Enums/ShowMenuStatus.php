<?php

namespace App\Enums;

use App\Enums\Traits\Commons;

enum ShowMenuStatus: int
{
    use Commons;

    case Yes = 1;
    case No = 2;
}
