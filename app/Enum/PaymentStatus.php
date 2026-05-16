<?php

namespace App\Enum;

enum PaymentStatus: string
{
    case Pending = 'Pending';
    case Paid = 'Paid';
    case Failed = 'Failed';
}
