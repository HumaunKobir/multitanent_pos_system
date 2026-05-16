<?php

namespace App\Enums;

use App\Enums\Traits\Commons;

enum TransactionType: int
{
    use Commons;

    case Opening_Balance = 3;
    case Income = 5;
    case CustomerDueCollection = 9;
    case PurchaseReturn = 10;
    case SupplierPurchase = 101;
    case Expense = 106;
    case SupplierPayment = 111;
    case CustomerSellDue = 112;
    case SaleReturn = 113;
    case Product_Exchange = 114;
    case Sale = 115;
    case OnlineOrderCollection = 116;
}
