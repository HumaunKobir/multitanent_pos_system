<?php

namespace App\Enums;

enum VoucherLineSide: string
{
    case Debit = 'debit';
    case Credit = 'credit';

    public function isDebit(): bool
    {
        return $this === self::Debit;
    }
}
