<?php

namespace App\Services;

use App\Enums\AccountType;

class AccountPostingRules
{
    /**
     * Wallet-style balance: debit line vs credit line determines decrease flag.
     */
    public static function decreaseForSide(AccountType $type, bool $isDebit): bool
    {
        return match ($type) {
            AccountType::Asset, AccountType::Expenses => $isDebit ? false : true,
            AccountType::Income => $isDebit ? true : false,
            AccountType::Liability, AccountType::Equity => $isDebit ? true : false,
        };
    }
}
