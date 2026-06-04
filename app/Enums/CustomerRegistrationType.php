<?php

namespace App\Enums;

use App\Enums\Traits\Commons;

enum CustomerRegistrationType: int
{
    use Commons;

    case Offline = 0;
    case Online = 1;
}
