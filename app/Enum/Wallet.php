<?php

namespace App\Enum;

enum Wallet: int
{
    case Debit = 0;
    case Credit = 1;
}
