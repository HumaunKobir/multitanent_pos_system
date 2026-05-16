<?php

namespace App\Enums;

use App\Enums\Traits\Commons;

enum UserStatus: int
{
    use Commons;

    case InActive = 0;
    case Active = 1;
}
