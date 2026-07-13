<?php

namespace App\Enums;

enum CoinTransactionType: string
{
    case Redeem = 'redeem';
    case Earn = 'earn';
    case ReverseRedeem = 'reverse_redeem';
    case ReverseEarn = 'reverse_earn';
    case Expire = 'expire';
}
