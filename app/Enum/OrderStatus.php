<?php

namespace App\Enum;

enum OrderStatus: int
{
    case Pending = 1;
    case Processing = 2;
    case Shipping = 3;
    case Delivered = 5;
    case Canceled = 6;
}
