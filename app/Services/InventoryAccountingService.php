<?php

namespace App\Services;

use App\Enums\AccountType;
use App\Enums\ReceivedPaymentMethod;
use App\Enums\SystemAccountKey;
use App\Models\Branch;
use App\Models\ChartOfAccount;
use App\Models\Customer;
use App\Models\CustomerPayment;
use App\Models\Damage;
use App\Models\Ledger;
use App\Models\OnlineOrder;
use App\Models\Product;
use App\Models\ProductExchange;
use App\Models\ProductInitialStock;
use App\Models\Purchase;
use App\Models\PurchaseReturn;
use App\Models\PurchaseReturnPayment;
use App\Models\SaleReturn;
use App\Models\SaleReturnPayment;
use App\Models\Sell;
use App\Models\StockDistribution;
use App\Models\StockDistributionProduct;
use App\Models\Supplier;
use App\Models\SupplierPayment;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class InventoryAccountingService
{
    public function __construct(private InventoryCostService $costService) {}

    public function postPurchase(Purchase $purchase, ?int $paymentAccountId, ?float $cashPaidAmount = null): ?Transaction
    {
        $purchase->loadMissing('supplier:id,name');

        $inventoryBase = round((float) $purchase->gross_amount - (float) $purchase->discount, 2);
        $vatAmount = round((float) $purchase->vat, 2);
        $inventoryTotal = round($inventoryBase + $vatAmount, 2);
        $paidAmount = round($cashPaidAmount ?? (float) $purchase->paid_amount, 2);
        $dueAmount = round(max(0, (float) $purchase->net_amount - $paidAmount), 2);
        $supplierName = $purchase->supplier?->name ?? 'Supplier';
        $serial = $purchase->serial ?? ('#'.$purchase->id);

        $lines = [];

        if ($inventoryTotal > 0) {
            $lines[] = $this->debitLine(SystemAccountKey::ProductInventory, $inventoryTotal, "Inventory increased — Purchase {$serial}, Supplier: {$supplierName}");
        }

        if ($paidAmount > 0) {
            $lines[] = $this->creditPaymentAccount($paymentAccountId, $paidAmount, "Cash paid — Purchase {$serial}");
        }

        if ($dueAmount > 0) {
            $lines[] = $this->creditLine(SystemAccountKey::SupplierPayables, $dueAmount, "Supplier payable — Purchase {$serial}, {$supplierName}");
        }

        if ($lines === []) {
            return null;
        }

        return $this->postJournal(
            Purchase::class,
            $purchase->id,
            $purchase->date->format('Y-m-d'),
            "Purchase {$serial}",
            $lines,
        );
    }

    public function postPurchaseReturn(PurchaseReturn $purchaseReturn, ?int $paymentAccountId = null): ?Transaction
    {
        $purchaseReturn->loadMissing('supplier:id,name');

        $returnBase = round(max(0, (float) $purchaseReturn->gross_amount - (float) $purchaseReturn->discount), 2);
        $vatAmount = round((float) $purchaseReturn->vat, 2);
        $returnNet = round((float) $purchaseReturn->net_amount, 2);
        $paidAmount = round(min(max(0, (float) $purchaseReturn->paid_amount), $returnNet), 2);
        // Use the stored due_amount — it may be lower than net-paid when part was offset against
        // the original purchase's outstanding due (tracked separately in purchase_due_offset).
        $dueAmount = round((float) $purchaseReturn->due_amount, 2);
        $purchaseDueOffset = round((float) $purchaseReturn->purchase_due_offset, 2);
        $serial = $purchaseReturn->serial ?? $purchaseReturn->invoice_number;
        $supplierName = $purchaseReturn->supplier?->name ?? 'Supplier';
        $branchId = $purchaseReturn->branch_id;

        $lines = [];

        // Portion the supplier refunded in cash/bank now (explicit account only — never default to cash in hand).
        if ($paidAmount > 0) {
            if ($paymentAccountId === null) {
                throw new \RuntimeException('Select a cash or bank account for the refund amount.');
            }

            $lines[] = $this->debitPaymentAccount($paymentAccountId, $paidAmount, "Cash received — Purchase Return {$serial}", $branchId);
        }

        // Only the offset portion clears an existing supplier payable (purchase due reversed).
        // The remaining return due is not yet received from the supplier, so it goes to
        // AccountsReceivable and will be cleared when the supplier actually pays (receivePayment).
        if ($purchaseDueOffset > 0) {
            $lines[] = $this->debitLine(SystemAccountKey::SupplierPayables, $purchaseDueOffset, "Purchase due reversed — Purchase Return {$serial}, {$supplierName}", $branchId);
        }

        if ($dueAmount > 0) {
            $lines[] = $this->debitLine(SystemAccountKey::AccountsReceivable, $dueAmount, "Supplier refund pending — Purchase Return {$serial}, {$supplierName}", $branchId);
        }

        if ($returnBase > 0) {
            $lines[] = $this->creditLine(SystemAccountKey::ProductInventory, $returnBase, "Inventory reduced — Purchase Return {$serial}", $branchId);
        }

        if ($vatAmount > 0) {
            $lines[] = $this->creditLine(SystemAccountKey::OutputVat, $vatAmount, "VAT reversed — Purchase Return {$serial}", $branchId);
        }

        if ($lines === []) {
            return null;
        }

        return $this->postJournal(
            PurchaseReturn::class,
            $purchaseReturn->id,
            $purchaseReturn->date->format('Y-m-d'),
            "Purchase Return {$serial}",
            $lines,
            validateBalance: false,
        );
    }

    /**
     * Record the cash/bank receipt when a supplier pays back a purchase return due.
     * Dr CashAccount, Cr AccountsReceivable — clears the receivable posted at return creation.
     */
    public function postPurchaseReturnPayment(PurchaseReturnPayment $payment): void
    {
        $serial = $payment->purchaseReturn?->invoice_number ?? "PR#{$payment->purchase_return_id}";
        $supplierName = $payment->supplier?->name ?? 'Supplier';
        $branchId = $payment->branch_id;
        $amount = round((float) $payment->amount, 2);

        $lines = [
            $this->debitPaymentAccount($payment->payment_account_id, $amount, "Supplier refund received — Purchase Return {$serial}, {$supplierName}", $branchId),
            $this->creditLine(SystemAccountKey::AccountsReceivable, $amount, "Supplier receivable cleared — Purchase Return {$serial}, {$supplierName}", $branchId),
        ];

        $this->postJournal(
            PurchaseReturnPayment::class,
            $payment->id,
            $payment->date->format('Y-m-d'),
            "Purchase Return Refund {$serial}",
            $lines,
            validateBalance: true,
        );
    }

    /**
     * @param  array<int, array{payment_account_id: int, amount: float}>  $paymentLines
     */
    public function postSale(
        Sell $sell,
        array $paymentLines,
        float $cogs,
        float $changeAmount = 0.0,
        ?int $changeAccountId = null,
    ): Transaction {
        $sell->loadMissing('customer:id,name');

        $salesBase = round(max(0, (float) $sell->net_amount - (float) $sell->vat), 2);
        $discountApplied = round(max(0, (float) $sell->gross_amount - $salesBase), 2);
        $coinRedeem = round(max(0, min((float) $sell->coin_discount_amount, $discountApplied)), 2);
        $commercialDiscount = round(max(0, $discountApplied - $coinRedeem), 2);
        $coinEarnLiability = $this->coinEarnLiabilityValue($sell);
        $salesGross = round($salesBase + $discountApplied, 2);
        $vatAmount = round((float) $sell->vat, 2);
        $paymentLineTotal = round(array_sum(array_map(
            fn (array $paymentLine): float => max(0, round((float) ($paymentLine['amount'] ?? 0), 2)),
            $paymentLines,
        )), 2);
        $dueAmount = round(max(0, (float) $sell->net_amount - $paymentLineTotal), 2);
        $invoice = $sell->invoice_number;
        $customerName = $sell->customer?->name ?? 'Customer';
        $branchId = $sell->branch_id;
        $changeAmount = round(max(0, $changeAmount), 2);

        $lines = [];

        foreach ($paymentLines as $paymentLine) {
            $lineAmount = round((float) $paymentLine['amount'], 2);

            if ($lineAmount <= 0) {
                continue;
            }

            $lines[] = $this->debitPaymentAccount(
                (int) $paymentLine['payment_account_id'],
                $lineAmount,
                "Payment received — Sale {$invoice}",
                $branchId,
            );
        }

        if ($changeAmount > 0) {
            $lines[] = $this->creditPaymentAccount(
                $changeAccountId ?? SystemAccountService::id(SystemAccountKey::CashInHand, $branchId),
                $changeAmount,
                "Change given — Sale {$invoice}",
                $branchId,
            );
        }

        if ($dueAmount > 0) {
            $lines[] = $this->debitLine(SystemAccountKey::CustomerReceivables, $dueAmount, "Receivable — Sale {$invoice}, {$customerName}", $branchId);
        }

        if ($commercialDiscount > 0) {
            $lines[] = $this->debitLine(SystemAccountKey::DiscountApplied, $commercialDiscount, "Discount applied — Sale {$invoice}", $branchId);
        }

        // Use coins: expense the money value on Coin Discount Applied, and reduce Customer Coin Payable.
        if ($coinRedeem > 0) {
            $lines[] = $this->debitLine(SystemAccountKey::CoinDiscountApplied, $coinRedeem, "Coin discount applied — Sale {$invoice}", $branchId);
            $lines[] = $this->debitLine(SystemAccountKey::CustomerCoinPayable, $coinRedeem, "Coin payable released — Sale {$invoice}", $branchId);
            $lines[] = $this->creditLine(SystemAccountKey::ProductSales, $coinRedeem, "Coin redeem sales offset — Sale {$invoice}", $branchId);
        }

        // Earn coins: store money value on Customer Coin Payable (liability). Coin Discount Applied does not change.
        if ($coinEarnLiability > 0) {
            $lines[] = $this->debitLine(SystemAccountKey::ProductSales, $coinEarnLiability, "Coin earn sales deferral — Sale {$invoice}", $branchId);
            $lines[] = $this->creditLine(SystemAccountKey::CustomerCoinPayable, $coinEarnLiability, "Coin earn payable — Sale {$invoice}", $branchId);
        }

        if ($salesGross > 0) {
            $lines[] = $this->creditLine(SystemAccountKey::ProductSales, $salesGross, "Sales revenue — Sale {$invoice}", $branchId);
        }

        if ($vatAmount > 0) {
            $lines[] = $this->creditLine(SystemAccountKey::OutputVat, $vatAmount, "Output VAT — Sale {$invoice}", $branchId);
        }

        if ($cogs > 0) {
            $lines[] = $this->debitLine(SystemAccountKey::CostOfGoodsSold, $cogs, "COGS — Sale {$invoice}", $branchId);
            $lines[] = $this->creditLine(SystemAccountKey::ProductInventory, $cogs, "Inventory reduced — Sale {$invoice}", $branchId);
        }

        return $this->postJournal(
            Sell::class,
            $sell->id,
            $sell->date->format('Y-m-d'),
            "Sale {$invoice}",
            $lines,
            validateBalance: false,
        );
    }

    /**
     * Record an incremental cash refund against an outstanding sale return due.
     * Dr Customer Receivables, Cr Cash — clears the store credit posted at return creation.
     */
    public function postSaleReturnRefundPayment(SaleReturnPayment $payment): void
    {
        $payment->loadMissing(['saleReturn.customer:id,name']);

        $saleReturn = $payment->saleReturn;
        $invoice = $saleReturn?->invoice_number ?? "SR#{$payment->sale_return_id}";
        $customerName = $saleReturn?->customer?->name ?? 'Customer';
        $branchId = $payment->branch_id ?? $saleReturn?->branch_id;
        $amount = round((float) $payment->amount, 2);
        $date = $payment->date ?? $saleReturn?->date ?? now();

        $lines = [
            $this->debitLine(SystemAccountKey::CustomerReceivables, $amount, "Refund payable cleared — Sale Return {$invoice}, {$customerName}", $branchId),
            $this->creditPaymentAccount($payment->payment_account_id, $amount, "Cash refunded — Sale Return {$invoice}, {$customerName}", $branchId),
        ];

        $this->postJournal(
            SaleReturnPayment::class,
            $payment->id,
            $date->format('Y-m-d'),
            "Sale Return Refund {$invoice}",
            $lines,
            validateBalance: false,
        );
    }

    public function postSaleReturn(SaleReturn $saleReturn, array $paymentLines, float $returnCost): Transaction
    {
        $saleReturn->loadMissing(['customer:id,name', 'sell:id,gross_amount,coin_discount_amount,coins_earned,coins_redeemed,branch_id']);

        $returnNet = round((float) $saleReturn->net_amount, 2);
        $paidAmount = round((float) $saleReturn->paid_amount, 2);
        $returnVat = round((float) $saleReturn->vat_amount, 2);
        $returnBase = round($returnNet - $returnVat, 2);
        $returnDiscount = round(max(0, (float) $saleReturn->discount_amount), 2);
        $coinRedeemReversed = $this->saleReturnCoinRedeemReversed($saleReturn);
        $coinEarnReversed = $this->saleReturnCoinEarnReversed($saleReturn);
        $returnGross = round($returnBase + $returnDiscount + $coinRedeemReversed, 2);

        $invoice = $saleReturn->invoice_number;
        $customerName = $saleReturn->customer?->name ?? 'Customer';
        $branchId = $saleReturn->branch_id;

        $lines = [];

        if ($returnGross > 0) {
            $lines[] = $this->debitLine(SystemAccountKey::SalesReturns, $returnGross, "Sales return — {$invoice}, {$customerName}", $branchId);
        }

        if ($returnDiscount > 0) {
            $lines[] = $this->creditLine(SystemAccountKey::DiscountApplied, $returnDiscount, "Discount reversed — Sale Return {$invoice}", $branchId);
        }

        // Reverse coin use: undo Coin Discount Applied expense and restore Customer Coin Payable.
        if ($coinRedeemReversed > 0) {
            $lines[] = $this->creditLine(SystemAccountKey::CoinDiscountApplied, $coinRedeemReversed, "Coin discount reversed — Sale Return {$invoice}", $branchId);
            $lines[] = $this->debitLine(SystemAccountKey::ProductSales, $coinRedeemReversed, "Coin redeem sales offset reversed — Sale Return {$invoice}", $branchId);
            $lines[] = $this->creditLine(SystemAccountKey::CustomerCoinPayable, $coinRedeemReversed, "Coin payable restored — Sale Return {$invoice}", $branchId);
        }

        // Reverse coin earn: remove Customer Coin Payable liability (Coin Discount Applied unchanged).
        if ($coinEarnReversed > 0) {
            $lines[] = $this->debitLine(SystemAccountKey::CustomerCoinPayable, $coinEarnReversed, "Coin earn payable reversed — Sale Return {$invoice}", $branchId);
            $lines[] = $this->creditLine(SystemAccountKey::ProductSales, $coinEarnReversed, "Coin earn sales deferral reversed — Sale Return {$invoice}", $branchId);
        }

        if ($returnVat > 0) {
            $lines[] = $this->debitLine(SystemAccountKey::OutputVat, $returnVat, "Output VAT reversed — {$invoice}", $branchId);
        }

        $cashCredit = 0.0;
        $arCredit = 0.0;

        foreach ($paymentLines as $paymentLine) {
            $lineAmount = round((float) $paymentLine['amount'], 2);

            if ($lineAmount <= 0) {
                continue;
            }

            $lines[] = $this->creditPaymentAccount(
                (int) $paymentLine['payment_account_id'],
                $lineAmount,
                "Cash refunded — Sale Return {$invoice}",
                $branchId,
            );
            $cashCredit = round($cashCredit + $lineAmount, 2);
        }

        if ($saleReturn->payment_type === ReceivedPaymentMethod::Customer_Account && $paidAmount > 0) {
            $arCredit = $paidAmount;
        }

        $remainingCredit = round(max(0, $returnNet - $cashCredit - $arCredit), 2);

        if ($remainingCredit > 0 && $saleReturn->customer_id) {
            $arCredit = round($arCredit + $remainingCredit, 2);
        } elseif ($remainingCredit > 0) {
            $fallbackAccountId = $paymentLines[0]['payment_account_id'] ?? $saleReturn->payment_account_id ?? null;
            if ($fallbackAccountId) {
                $lines[] = $this->creditPaymentAccount(
                    (int) $fallbackAccountId,
                    $remainingCredit,
                    "Cash refunded — Sale Return {$invoice}",
                    $branchId,
                );
                $cashCredit = round($cashCredit + $remainingCredit, 2);
            }
        }

        if ($arCredit > 0) {
            $lines[] = $this->creditLine(SystemAccountKey::CustomerReceivables, $arCredit, "Receivable reduced — Sale Return {$invoice}, {$customerName}", $branchId);
        }

        if ($returnCost > 0) {
            $lines[] = $this->debitLine(SystemAccountKey::ProductInventory, $returnCost, "Inventory restored — Sale Return {$invoice}", $branchId);
            $lines[] = $this->creditLine(SystemAccountKey::CostOfGoodsSold, $returnCost, "COGS reversed — Sale Return {$invoice}", $branchId);
        }

        return $this->postJournal(
            SaleReturn::class,
            $saleReturn->id,
            $saleReturn->date->format('Y-m-d'),
            "Sale Return {$invoice}",
            $lines,
            // A sale return legitimately credits cash (refund out) and Customer Receivables
            // (store credit / reduced due), which can drive those balances down or negative.
            // Mirror postSale / postPurchaseReturn / postExchange and skip the decrease guard,
            // otherwise a low receivable or cash balance rolls back the entire return.
            validateBalance: false,
        );
    }

    public function postDamage(Damage $damage, float $totalCost): Transaction
    {
        $serial = $damage->serial ?? $damage->invoice_number;

        $lines = [
            $this->debitLine(SystemAccountKey::InventoryDamage, $totalCost, "Inventory write-off — Damage {$serial}"),
            $this->creditLine(SystemAccountKey::ProductInventory, $totalCost, "Inventory reduced — Damage {$serial}"),
        ];

        return $this->postJournal(
            Damage::class,
            $damage->id,
            $damage->date->format('Y-m-d'),
            "Damage {$serial}",
            $lines,
        );
    }

    public function postStockDistribution(StockDistribution $distribution, float $totalCost): Transaction
    {
        $distribution->loadMissing('toBranch:id,name');

        $fromBranchId = $distribution->from_branch_id;
        $toBranchId = $distribution->to_branch_id;
        $totalCost = round($totalCost, 2);

        if ($totalCost <= 0) {
            throw new \RuntimeException('Stock distribution cost must be greater than zero.');
        }

        SystemAccountService::ensureConfigured($fromBranchId);
        SystemAccountService::ensureConfigured($toBranchId);

        $serial = $distribution->serial ?? $distribution->invoice_number;
        $branchName = $distribution->toBranch?->name ?? 'Branch';

        $lines = [
            $this->debitAccount(
                SystemAccountService::resolve(SystemAccountKey::ProductInventory, $toBranchId),
                $totalCost,
                "Branch inventory increased — Distribution {$serial}, {$branchName}",
            ),
            $this->creditAccount(
                SystemAccountService::resolve(SystemAccountKey::ProductInventory, $fromBranchId),
                $totalCost,
                "Main inventory reduced — Distribution {$serial}, to {$branchName}",
            ),
            $this->debitAccount(
                SystemAccountService::resolve(SystemAccountKey::IntercompanyReceivable, $fromBranchId),
                $totalCost,
                "Intercompany receivable — Distribution {$serial}, {$branchName}",
            ),
            $this->creditAccount(
                SystemAccountService::resolve(SystemAccountKey::IntercompanyPayable, $toBranchId),
                $totalCost,
                "Intercompany payable — Distribution {$serial}, to {$branchName}",
            ),
        ];

        return $this->postJournal(
            StockDistribution::class,
            $distribution->id,
            $distribution->date->format('Y-m-d'),
            "Stock Distribution {$serial}",
            $lines,
            validateBalance: false,
        );
    }

    public function postStockDistributionLine(
        StockDistribution $distribution,
        StockDistributionProduct $line,
        float $totalCost,
    ): Transaction {
        $distribution->loadMissing('toBranch:id,name');

        $fromBranchId = $distribution->from_branch_id;
        $toBranchId = $distribution->to_branch_id;
        $totalCost = round($totalCost, 2);

        if ($totalCost <= 0) {
            throw new \RuntimeException('Stock distribution line cost must be greater than zero.');
        }

        SystemAccountService::ensureConfigured($fromBranchId);
        SystemAccountService::ensureConfigured($toBranchId);

        $serial = $distribution->serial ?? $distribution->invoice_number;
        $branchName = $distribution->toBranch?->name ?? 'Branch';
        $line->loadMissing('product:id,name');

        $productName = $line->product?->name ?? 'Product';

        $lines = [
            $this->debitAccount(
                SystemAccountService::resolve(SystemAccountKey::ProductInventory, $toBranchId),
                $totalCost,
                "Branch inventory increased — Distribution {$serial}, {$branchName}, {$productName}",
            ),
            $this->creditAccount(
                SystemAccountService::resolve(SystemAccountKey::ProductInventory, $fromBranchId),
                $totalCost,
                "Main inventory reduced — Distribution {$serial}, to {$branchName}, {$productName}",
            ),
            $this->debitAccount(
                SystemAccountService::resolve(SystemAccountKey::IntercompanyReceivable, $fromBranchId),
                $totalCost,
                "Intercompany receivable — Distribution {$serial}, {$branchName}, {$productName}",
            ),
            $this->creditAccount(
                SystemAccountService::resolve(SystemAccountKey::IntercompanyPayable, $toBranchId),
                $totalCost,
                "Intercompany payable — Distribution {$serial}, to {$branchName}, {$productName}",
            ),
        ];

        return $this->postJournal(
            StockDistributionProduct::class,
            $line->id,
            $distribution->date->format('Y-m-d'),
            "Stock Distribution {$serial} — {$productName}",
            $lines,
            validateBalance: false,
        );
    }

    public function reverseStockDistributionLines(StockDistribution $distribution): void
    {
        $distribution->loadMissing('products');

        foreach ($distribution->products as $line) {
            $this->reverseFor($line);
        }

        $this->reverseFor($distribution);
    }

    public function postSupplierPayment(SupplierPayment $payment, int $paymentAccountId): Transaction
    {
        $payment->loadMissing('supplier:id,name');

        $branchId = $payment->branch_id;

        $amount = round((float) $payment->amount, 2);
        $serial = $payment->serial ?? $payment->invoice_number;
        $supplierName = $payment->supplier?->name ?? 'Supplier';

        $lines = [
            $this->debitLine(SystemAccountKey::SupplierPayables, $amount, "Payable reduced — Payment {$serial}, {$supplierName}", $branchId),
            $this->creditPaymentAccount($paymentAccountId, $amount, "Cash paid — Payment {$serial}", $branchId),
        ];

        return $this->postJournal(
            SupplierPayment::class,
            $payment->id,
            $payment->date->format('Y-m-d'),
            "Supplier Payment {$serial}",
            $lines,
        );
    }

    public function postCustomerPayment(CustomerPayment $payment, int $paymentAccountId): Transaction
    {
        $payment->loadMissing('customer:id,name');

        $branchId = $payment->branch_id;

        $amount = round((float) $payment->amount, 2);
        $serial = $payment->serial ?? $payment->invoice_number;
        $customerName = $payment->customer?->name ?? 'Customer';

        $lines = [
            $this->debitPaymentAccount($paymentAccountId, $amount, "Cash received — Collection {$serial}"),
            $this->creditLine(SystemAccountKey::CustomerReceivables, $amount, "Receivable reduced — Collection {$serial}, {$customerName}", $branchId),
        ];

        return $this->postJournal(
            CustomerPayment::class,
            $payment->id,
            $payment->date->format('Y-m-d'),
            "Customer Due Collection {$serial}",
            $lines,
        );
    }

    public function postOnlineOrderPrepayment(OnlineOrder $order, int $paymentAccountId): Transaction
    {
        $branchId = EcommerceBranchService::resolveIdStatic();
        $amount = round((float) $order->total, 2);
        $label = $this->onlineOrderLabel($order);

        $lines = [
            $this->debitPaymentAccount($paymentAccountId, $amount, "Online prepayment received — {$label}", $branchId),
            $this->creditLine(SystemAccountKey::AdvanceFromCustomer, $amount, "Customer deposit — {$label}", $branchId),
        ];

        return $this->postOnlineOrderJournal($order, 'prepayment', $lines);
    }

    public function postOnlineOrderFulfillment(OnlineOrder $order, ?int $paymentAccountId, float $cogs): Transaction
    {
        $branchId = EcommerceBranchService::resolveIdStatic();
        $subtotal = round((float) $order->subtotal, 2);
        $deliveryCharge = round((float) $order->delivery_charge, 2);
        $total = round((float) $order->total, 2);
        $label = $this->onlineOrderLabel($order);
        $lines = [];

        if ($order->payment_method === 'sslcommerz' && $order->payment_status === 'Paid') {
            if ($total > 0) {
                $lines[] = $this->debitLine(SystemAccountKey::AdvanceFromCustomer, $total, "Revenue recognition — {$label}", $branchId);
            }
        } elseif ($order->payment_method === 'cod') {
            if ($total > 0) {
                $lines[] = $this->debitPaymentAccount($paymentAccountId, $total, "COD collection — {$label}", $branchId);
            }
        } else {
            throw new \RuntimeException('Unsupported online order payment method for fulfillment.');
        }

        if ($subtotal > 0) {
            $lines[] = $this->creditLine(SystemAccountKey::ProductSales, $subtotal, "Product sales — {$label}", $branchId);
        }

        if ($deliveryCharge > 0) {
            $lines[] = $this->creditLine(SystemAccountKey::OtherIncome, $deliveryCharge, "Delivery revenue — {$label}", $branchId);
        }

        if ($cogs > 0) {
            $lines[] = $this->debitLine(SystemAccountKey::CostOfGoodsSold, $cogs, "COGS — {$label}", $branchId);
            $lines[] = $this->creditLine(SystemAccountKey::ProductInventory, $cogs, "Inventory reduced — {$label}", $branchId);
        }

        return $this->postOnlineOrderJournal($order, 'fulfillment', $lines);
    }

    public function reverseOnlineOrderPrepayment(OnlineOrder $order, int $paymentAccountId): Transaction
    {
        $branchId = EcommerceBranchService::resolveIdStatic();
        $amount = round((float) $order->total, 2);
        $label = $this->onlineOrderLabel($order);

        $lines = [
            $this->debitLine(SystemAccountKey::AdvanceFromCustomer, $amount, "Prepayment reversed — {$label}", $branchId),
            $this->creditPaymentAccount($paymentAccountId, $amount, "Prepayment refunded — {$label}", $branchId),
        ];

        return $this->postOnlineOrderJournal($order, 'prepayment_reversal', $lines);
    }

    public function findOnlineOrderTransaction(OnlineOrder $order, string $phase): ?Transaction
    {
        $prefix = match ($phase) {
            'prepayment' => 'Online order prepayment —',
            'fulfillment' => 'Online order fulfillment —',
            'prepayment_reversal' => 'Online order prepayment reversal —',
            default => throw new \InvalidArgumentException("Unknown online order accounting phase: {$phase}"),
        };

        return Transaction::query()
            ->where('source_type', OnlineOrder::class)
            ->where('source_id', $order->id)
            ->where('description', 'like', $prefix.'%')
            ->first();
    }

    /**
     * @param  array<int, array{account_id: int, debit: float, credit: float, decrease: bool, description?: ?string}>  $lines
     */
    private function postOnlineOrderJournal(OnlineOrder $order, string $phase, array $lines): Transaction
    {
        $description = match ($phase) {
            'prepayment' => 'Online order prepayment — '.$this->onlineOrderLabel($order),
            'fulfillment' => 'Online order fulfillment — '.$this->onlineOrderLabel($order),
            'prepayment_reversal' => 'Online order prepayment reversal — '.$this->onlineOrderLabel($order),
            default => throw new \InvalidArgumentException("Unknown online order accounting phase: {$phase}"),
        };

        return $this->postJournal(
            OnlineOrder::class,
            $order->id,
            now()->format('Y-m-d'),
            $description,
            $lines,
        );
    }

    private function onlineOrderLabel(OnlineOrder $order): string
    {
        return 'Order #'.$order->id;
    }

    public function postExchange(ProductExchange $exchange, ?int $paymentAccountId): Transaction
    {
        $exchange->loadMissing(['customer:id,name', 'sell:id,gross_amount,discount,vat', 'products']);

        $amounts = $this->buildExchangeAmounts($exchange);

        $invoice = $exchange->invoice_number;
        $customerName = $exchange->customer?->name ?? 'Customer';
        $lines = [];

        if ($amounts['old_base'] > 0) {
            $lines[] = $this->debitLine(SystemAccountKey::SalesReturns, $amounts['old_base'], "Exchange old revenue reversed — {$invoice}");
        }

        if ($amounts['old_vat'] > 0) {
            $lines[] = $this->debitLine(SystemAccountKey::OutputVat, $amounts['old_vat'], "Exchange old VAT reversed — {$invoice}");
        }

        if ($amounts['new_base'] > 0) {
            $lines[] = $this->creditLine(SystemAccountKey::ProductSales, $amounts['new_base'], "Exchange new revenue — {$invoice}");
        }

        if ($amounts['new_vat'] > 0) {
            $lines[] = $this->creditLine(SystemAccountKey::OutputVat, $amounts['new_vat'], "Exchange new VAT — {$invoice}");
        }

        if ($amounts['old_cost'] > 0) {
            $lines[] = $this->debitLine(SystemAccountKey::ProductInventory, $amounts['old_cost'], "Inventory restored — Exchange {$invoice}");
            $lines[] = $this->creditLine(SystemAccountKey::CostOfGoodsSold, $amounts['old_cost'], "COGS reversed — Exchange {$invoice}");
        }

        if ($amounts['new_cost'] > 0) {
            $lines[] = $this->debitLine(SystemAccountKey::CostOfGoodsSold, $amounts['new_cost'], "COGS — Exchange {$invoice}");
            $lines[] = $this->creditLine(SystemAccountKey::ProductInventory, $amounts['new_cost'], "Inventory reduced — Exchange {$invoice}");
        }

        $priceDifference = round((float) $amounts['price_difference'], 2);
        $paidAmount = round(max(0, (float) $exchange->paid_amount), 2);
        $dueAmount = round(max(0, (float) ($exchange->due_amount ?? 0)), 2);

        if ($exchange->payment_type === ReceivedPaymentMethod::Customer_Account) {
            if ($priceDifference > 0) {
                $lines[] = $this->debitLine(SystemAccountKey::CustomerReceivables, $priceDifference, "Receivable — Exchange {$invoice}, {$customerName}");
            } elseif ($priceDifference < 0) {
                $lines[] = $this->creditLine(SystemAccountKey::CustomerReceivables, abs($priceDifference), "Receivable reduced — Exchange {$invoice}, {$customerName}");
            }
        } elseif ($priceDifference > 0) {
            if ($paidAmount > 0) {
                $lines[] = $this->debitPaymentAccount($paymentAccountId, $paidAmount, "Cash received — Exchange {$invoice}");
            }

            if ($dueAmount > 0) {
                $lines[] = $this->debitLine(SystemAccountKey::CustomerReceivables, $dueAmount, "Receivable — Exchange {$invoice}, {$customerName}");
            }

            if ($paidAmount <= 0 && $dueAmount <= 0) {
                $lines[] = $this->debitPaymentAccount($paymentAccountId, $priceDifference, "Cash received — Exchange {$invoice}");
            }
        } elseif ($priceDifference < 0) {
            $journalRefund = abs($priceDifference);

            if ($paidAmount > 0) {
                $cashRefund = round(min($paidAmount, $journalRefund), 2);
                $lines[] = $this->creditPaymentAccount($paymentAccountId, $cashRefund, "Cash refunded — Exchange {$invoice}");
                $journalRefund = round($journalRefund - $cashRefund, 2);
            }

            if ($journalRefund > 0) {
                if ($dueAmount > 0 || $paidAmount <= 0) {
                    $lines[] = $this->creditLine(SystemAccountKey::CustomerReceivables, $journalRefund, "Receivable reduced — Exchange {$invoice}, {$customerName}");
                } else {
                    $lines[] = $this->creditPaymentAccount($paymentAccountId, $journalRefund, "Cash refunded — Exchange {$invoice}");
                }
            }
        }

        return $this->postJournal(
            ProductExchange::class,
            $exchange->id,
            $exchange->date->format('Y-m-d'),
            "Product Exchange {$invoice}",
            $lines,
            false,
        );
    }

    /**
     * When an edit shrinks a refund below what was already paid out in cash on a
     * prior save, the excess doesn't vanish — it becomes a customer receivable.
     * Posted as its own balanced journal (rather than an extra line on the main
     * exchange journal) so it stays self-consistent and is swept up by
     * reverseFor() on the next edit or delete like any other exchange posting.
     */
    public function postExchangeOverpaymentReceivable(ProductExchange $exchange, int $paymentAccountId, float $amount): Transaction
    {
        $amount = round($amount, 2);
        $exchange->loadMissing('customer:id,name');
        $invoice = $exchange->invoice_number;
        $customerName = $exchange->customer?->name ?? 'Customer';

        $lines = [
            $this->debitLine(SystemAccountKey::CustomerReceivables, $amount, "Receivable — Exchange overpayment {$invoice}, {$customerName}"),
            $this->creditPaymentAccount($paymentAccountId, $amount, "Overpaid refund recovered — Exchange {$invoice}"),
        ];

        return $this->postJournal(
            ProductExchange::class,
            $exchange->id,
            $exchange->date->format('Y-m-d'),
            "Product Exchange overpayment {$invoice}",
            $lines,
            false,
        );
    }

    /**
     * @return array{
     *   old_base: float,
     *   old_vat: float,
     *   new_base: float,
     *   new_vat: float,
     *   old_cost: float,
     *   new_cost: float,
     *   invoice_discount: float,
     *   special_discount: float,
     *   round_off: float,
     *   paid_amount: float,
     *   price_difference: float
     * }
     */
    public function buildExchangeAmounts(ProductExchange $exchange): array
    {
        $parent = $exchange->sell;
        $parentNet = $parent ? (float) $parent->net_amount : (float) $exchange->gross_amount;
        $parentVat = $parent ? (float) $parent->vat : 0.0;
        $vatRatio = $parentNet > 0 ? $parentVat / $parentNet : 0.0;

        $oldGross = 0.0;
        $newGross = 0.0;
        $oldCost = 0.0;
        $newCost = 0.0;

        foreach ($exchange->products as $line) {
            // The reversal (old) side covers everything taken back from the sale:
            // the swapped-out quantity and any returned/refunded quantity.
            $takenBackQty = (float) $line->old_quantity + (float) $line->return_quantity;
            $oldGross += $takenBackQty * (float) $line->old_unit_price;
            $newGross += (float) $line->new_quantity * (float) $line->new_unit_price;
            $oldCost += $this->costService->costForLine(
                $line->old_variation_id ? (int) $line->old_variation_id : null,
                (float) $line->old_quantity,
                $line->old_batches ?? [],
            );
            $oldCost += $this->costService->costForLine(
                $line->old_variation_id ? (int) $line->old_variation_id : null,
                (float) $line->return_quantity,
                $line->return_batches ?? [],
            );
            $newCost += $this->costService->costForLine(
                $line->new_variation_id ? (int) $line->new_variation_id : null,
                (float) $line->new_quantity,
                $line->new_batches ?? [],
            );
        }

        $invoiceDiscount = (float) $exchange->discount;
        $specialDiscount = (float) $exchange->special_discount_amount;
        $roundOff = (float) $exchange->round_off_amount;
        $exchangeVat = (float) $exchange->vat;

        $newNet = max(0, (float) $exchange->net_amount);
        $newVat = $exchangeVat > 0 ? $exchangeVat : round($newNet * $vatRatio, 2);
        $newBase = round(max(0, $newNet - $newVat), 2);

        // Balance the old-side revenue reversal against the actual customer
        // settlement so the journal is always balanced: reversing the old goods
        // plus the settlement the customer pays/refunds must equal the new
        // revenue recognized. The stored price_difference is the gross-based,
        // VAT-excluded amount the customer settles; deriving the reversal from it
        // keeps the double entry balanced even when the sale carried VAT,
        // invoice, special, or round-off discounts, or when only some of a
        // multi-product sale's lines are exchanged.
        $priceDifference = round((float) $exchange->price_difference, 2);
        $oldNetTotal = round(max(0, $newNet - $priceDifference), 2);

        [$oldBase, $oldVat] = $this->splitVat($oldNetTotal, $vatRatio);

        return [
            'old_base' => $oldBase,
            'old_vat' => $oldVat,
            'new_base' => $newBase,
            'new_vat' => $newVat,
            'old_cost' => round($oldCost, 2),
            'new_cost' => round($newCost, 2),
            'invoice_discount' => $invoiceDiscount,
            'special_discount' => $specialDiscount,
            'round_off' => $roundOff,
            'paid_amount' => (float) $exchange->paid_amount,
            'price_difference' => round((float) $exchange->price_difference, 2),
        ];
    }

    /**
     * @return array{0: float, 1: float}
     */
    private function splitVat(float $gross, float $vatRatio): array
    {
        $vat = round($gross * $vatRatio, 2);

        return [round($gross - $vat, 2), $vat];
    }

    public function postAccountOpeningBalance(ChartOfAccount $account, float $amount, string $date): ?Transaction
    {
        if ($amount <= 0) {
            return null;
        }

        $amount = round($amount, 2);
        $equity = SystemAccountKey::OpeningBalanceClearing;
        $branchId = $account->source_type === Branch::class ? (int) $account->source_id : null;

        $lines = match ($account->type) {
            AccountType::Asset => [
                $this->debitAccount($account, $amount, "Opening balance — {$account->name}"),
                $this->creditLine($equity, $amount, "Opening balance offset — {$account->name}", $branchId),
            ],
            AccountType::Liability, AccountType::Income => [
                $this->debitLine($equity, $amount, "Opening balance offset — {$account->name}", $branchId),
                $this->creditAccount($account, $amount, "Opening balance — {$account->name}"),
            ],
            AccountType::Equity => [
                $this->debitLine($equity, $amount, "Opening balance offset — {$account->name}", $branchId),
                $this->creditAccount($account, $amount, "Opening balance — {$account->name}"),
            ],
            AccountType::Expenses => [
                $this->debitAccount($account, $amount, "Opening balance — {$account->name}"),
                $this->creditLine($equity, $amount, "Opening balance offset — {$account->name}", $branchId),
            ],
        };

        $transaction = $this->postJournal(
            ChartOfAccount::class,
            $account->id,
            $date,
            "Opening balance — {$account->name}",
            $lines,
            validateBalance: false,
        );

        $this->closeOpeningBalanceClearingToCapital(
            $date,
            ChartOfAccount::class,
            $account->id,
            $branchId,
        );

        return $transaction;
    }

    public function postSupplierOpeningBalance(Supplier $supplier, float $amount, string $date): ?Transaction
    {
        if ($amount <= 0) {
            return null;
        }

        $branchId = $supplier->branch_id;

        $amount = round($amount, 2);

        $lines = [
            $this->debitLine(SystemAccountKey::OpeningBalanceClearing, $amount, "Opening balance offset — Supplier {$supplier->name}", $branchId),
            $this->creditLine(SystemAccountKey::SupplierPayables, $amount, "Supplier opening payable — {$supplier->name}", $branchId),
        ];

        $transaction = $this->postJournal(
            Supplier::class,
            $supplier->id,
            $date,
            "Supplier opening balance — {$supplier->name}",
            $lines,
            validateBalance: false,
        );

        $this->closeOpeningBalanceClearingToCapital(
            $date,
            Supplier::class,
            $supplier->id,
            $branchId,
        );

        return $transaction;
    }

    public function postCustomerOpeningBalance(Customer $customer, float $amount, string $date): ?Transaction
    {
        if ($amount <= 0) {
            return null;
        }

        $branchId = $customer->branch_id;

        $amount = round($amount, 2);

        $lines = [
            $this->debitLine(SystemAccountKey::CustomerReceivables, $amount, "Customer opening receivable — {$customer->name}", $branchId),
            $this->creditLine(SystemAccountKey::OpeningBalanceClearing, $amount, "Opening balance offset — Customer {$customer->name}", $branchId),
        ];

        $transaction = $this->postJournal(
            Customer::class,
            $customer->id,
            $date,
            "Customer opening balance — {$customer->name}",
            $lines,
            validateBalance: false,
        );

        $this->closeOpeningBalanceClearingToCapital(
            $date,
            Customer::class,
            $customer->id,
            $branchId,
        );

        return $transaction;
    }

    public function postProductInitialStockMovement(
        ProductInitialStock $record,
        float $amount,
        bool $increase,
        string $productLabel,
    ): ?Transaction {
        if ($amount <= 0) {
            return null;
        }

        $amount = round($amount, 2);
        $branchId = $record->branch_id;
        $direction = $increase ? 'increased' : 'reduced';
        $date = now()->format('Y-m-d');

        $lines = $increase
            ? [
                $this->debitLine(SystemAccountKey::ProductInventory, $amount, "Initial stock {$direction} — {$productLabel}", $branchId),
                $this->creditLine(SystemAccountKey::OpeningBalanceClearing, $amount, "Opening balance offset — Initial stock {$productLabel}", $branchId),
            ]
            : [
                $this->debitLine(SystemAccountKey::OpeningBalanceClearing, $amount, "Opening balance offset — Initial stock {$productLabel}", $branchId),
                $this->creditLine(SystemAccountKey::ProductInventory, $amount, "Initial stock {$direction} — {$productLabel}", $branchId),
            ];

        $transaction = $this->postJournal(
            ProductInitialStock::class,
            $record->id ?? 0,
            $date,
            "Product initial stock — {$productLabel}",
            $lines,
            validateBalance: $increase,
        );

        $this->closeOpeningBalanceClearingToCapital(
            $date,
            ProductInitialStock::class,
            $record->id ?? 0,
            $branchId,
        );

        return $transaction;
    }

    public function postProductInitialStockSupplierSettlement(
        Product $product,
        float $inventoryTotal,
        float $paidAmount,
        ?int $paymentAccountId,
        string $supplierName,
    ): ?Transaction {
        if ($inventoryTotal <= 0) {
            return null;
        }

        $inventoryTotal = round($inventoryTotal, 2);
        $branchId = $product->branch_id;

        $lines = [
            $this->debitLine(SystemAccountKey::ProductInventory, $inventoryTotal, "Initial stock — {$product->name}", $branchId),
            $this->creditLine(SystemAccountKey::SupplierPayables, $inventoryTotal, "Supplier payable — Initial stock {$product->name}, {$supplierName}", $branchId),
        ];

        return $this->postJournal(
            Product::class,
            $product->id,
            now()->format('Y-m-d'),
            "Product initial stock — {$product->name}",
            $lines,
        );
    }

    public function postProductInitialStockOpeningBalance(
        Product $product,
        float $amount,
    ): ?Transaction {
        if ($amount <= 0) {
            return null;
        }

        $amount = round($amount, 2);
        $branchId = $product->branch_id;
        $date = now()->format('Y-m-d');

        $lines = [
            $this->debitLine(SystemAccountKey::ProductInventory, $amount, "Initial stock — {$product->name}", $branchId),
            $this->creditLine(SystemAccountKey::OpeningBalanceClearing, $amount, "Opening balance offset — Initial stock {$product->name}", $branchId),
        ];

        $transaction = $this->postJournal(
            Product::class,
            $product->id,
            $date,
            "Product initial stock — {$product->name}",
            $lines,
        );

        $this->closeOpeningBalanceClearingToCapital(
            $date,
            Product::class,
            $product->id,
            $branchId,
        );

        return $transaction;
    }

    /**
     * Transfer residual Opening Balance Clearing into Owner's Capital so the
     * temporary clearing account returns to zero after opening entries.
     */
    public function closeOpeningBalanceClearingToCapital(
        string $date,
        string $sourceType,
        int|string $sourceId,
        ?int $branchId = null,
    ): ?Transaction {
        $clearing = SystemAccountService::resolve(SystemAccountKey::OpeningBalanceClearing, $branchId);
        $balance = round((float) $clearing->fresh()->current_balance, 2);

        if (abs($balance) < 0.005) {
            return null;
        }

        $amount = abs($balance);

        $lines = $balance > 0
            ? [
                $this->debitLine(SystemAccountKey::OpeningBalanceClearing, $amount, 'Close opening balance clearing to capital', $branchId),
                $this->creditLine(SystemAccountKey::OwnersCapital, $amount, "Opening equity transferred to Owner's Capital", $branchId),
            ]
            : [
                $this->debitLine(SystemAccountKey::OwnersCapital, $amount, "Opening equity transferred from Owner's Capital", $branchId),
                $this->creditLine(SystemAccountKey::OpeningBalanceClearing, $amount, 'Close opening balance clearing to capital', $branchId),
            ];

        return $this->postJournal(
            $sourceType,
            $sourceId,
            $date,
            "Close Opening Balance Clearing to Owner's Capital",
            $lines,
            validateBalance: false,
        );
    }

    public function reverseFor(Model $source): void
    {
        $transactionIds = Transaction::query()
            ->where('source_type', $source::class)
            ->where('source_id', $source->getKey())
            ->orderByDesc('id')
            ->pluck('id');

        foreach ($transactionIds as $transactionId) {
            $transaction = Transaction::query()->find($transactionId);

            if ($transaction !== null) {
                TransactionService::reverseTransaction($transaction);
            }
        }
    }

    public function findTransactionFor(Model $source): ?Transaction
    {
        return Transaction::query()
            ->where('source_type', $source::class)
            ->where('source_id', $source->getKey())
            ->latest('id')
            ->first();
    }

    public function paymentAccountIdFor(Model $source, bool $latest = false): ?int
    {
        $query = Transaction::query()
            ->where('source_type', $source::class)
            ->where('source_id', $source->getKey());

        $transaction = $latest ? $query->latest('id')->first() : $query->first();

        if ($transaction === null) {
            return null;
        }

        $ledgers = Ledger::query()
            ->where('transaction_id', $transaction->id)
            ->with('account')
            ->get();

        $accountId = null;

        if ($source instanceof Purchase || $source instanceof SupplierPayment) {
            $accountId = $ledgers
                ->first(fn (Ledger $line) => $line->credit > 0 && $line->account?->isPaymentAccount())
                ?->account_id;
        } elseif ($source instanceof CustomerPayment || $source instanceof Sell) {
            $accountId = $ledgers
                ->first(fn (Ledger $line) => $line->debit > 0 && $line->account?->isPaymentAccount())
                ?->account_id;
        }

        if ($accountId === null) {
            $accountId = $ledgers
                ->first(fn (Ledger $line) => ($line->debit > 0 || $line->credit > 0) && $line->account?->isPaymentAccount())
                ?->account_id;
        }

        return $accountId !== null ? (int) $accountId : null;
    }

    /**
     * @param  array<int, array{account_id: int, debit: float, credit: float, decrease: bool, description?: ?string}>  $lines
     */
    private function postJournal(string $sourceType, int|string $sourceId, string $date, string $description, array $lines, bool $validateBalance = true): Transaction
    {
        $lines = array_values(array_filter($lines, fn (array $line) => ($line['debit'] ?? 0) > 0 || ($line['credit'] ?? 0) > 0));

        if ($lines === []) {
            throw new \RuntimeException('Accounting journal has no lines.');
        }

        $performedBy = $this->performedByPayload();

        return TransactionService::recordJournalEntry([
            'source_type' => $sourceType,
            'source_id' => $sourceId,
            ...$performedBy,
            'date' => $date,
            'description' => $description,
        ], $lines, $validateBalance);
    }

    /**
     * @return array{performed_by_type?: string, performed_by_id?: int}
     */
    private function performedByPayload(): array
    {
        $userId = Auth::id();

        if (! $userId) {
            return [];
        }

        return [
            'performed_by_type' => User::class,
            'performed_by_id' => $userId,
        ];
    }

    private function debitLine(SystemAccountKey $key, float $amount, string $description, ?int $branchId = null): array
    {
        return $this->line(SystemAccountService::resolve($key, $branchId), $amount, 0.0, true, $description);
    }

    /**
     * Money value of coins earned on a sale (coins_earned × coin_value).
     */
    private function coinEarnLiabilityValue(Sell $sell): float
    {
        $coinsEarned = round(max(0, (float) $sell->coins_earned), 2);

        if ($coinsEarned <= 0) {
            return 0.0;
        }

        $coinValue = $this->resolveCoinValue($sell);

        if ($coinValue <= 0) {
            return 0.0;
        }

        return round($coinsEarned * $coinValue, 2);
    }

    /**
     * Prefer implied coin value from this sale's redeem; otherwise branch coin settings.
     */
    private function resolveCoinValue(Sell $sell): float
    {
        $redeemed = round(max(0, (float) $sell->coins_redeemed), 2);
        $redeemValue = round(max(0, (float) $sell->coin_discount_amount), 2);

        if ($redeemed > 0 && $redeemValue > 0) {
            return round($redeemValue / $redeemed, 6);
        }

        $settings = app(CoinService::class)->settingsForBranch($sell->branch_id);

        return round(max(0, (float) ($settings?->coin_value ?? 0)), 2);
    }

    /**
     * Reverse a proportional share of the parent sale's coin redeem on return.
     */
    private function saleReturnCoinRedeemReversed(SaleReturn $saleReturn): float
    {
        $sell = $saleReturn->sell;
        $parentCoinRedeem = round(max(0, (float) ($sell?->coin_discount_amount ?? 0)), 2);

        if ($sell === null || $parentCoinRedeem <= 0) {
            return 0.0;
        }

        return round($parentCoinRedeem * $this->saleReturnProportion($saleReturn), 2);
    }

    /**
     * Reverse a proportional share of the parent sale's coin earn liability on return.
     */
    private function saleReturnCoinEarnReversed(SaleReturn $saleReturn): float
    {
        $sell = $saleReturn->sell;

        if ($sell === null) {
            return 0.0;
        }

        $parentEarn = $this->coinEarnLiabilityValue($sell);

        if ($parentEarn <= 0) {
            return 0.0;
        }

        return round($parentEarn * $this->saleReturnProportion($saleReturn), 2);
    }

    private function saleReturnProportion(SaleReturn $saleReturn): float
    {
        $parentGross = round(max(0, (float) ($saleReturn->sell?->gross_amount ?? 0)), 2);

        if ($parentGross <= 0) {
            return 0.0;
        }

        return min(1.0, round((float) $saleReturn->gross_amount / $parentGross, 4));
    }

    private function creditLine(SystemAccountKey $key, float $amount, string $description, ?int $branchId = null): array
    {
        return $this->line(SystemAccountService::resolve($key, $branchId), 0.0, $amount, false, $description);
    }

    private function debitAccount(ChartOfAccount $account, float $amount, string $description): array
    {
        return $this->line($account, $amount, 0.0, true, $description);
    }

    private function creditAccount(ChartOfAccount $account, float $amount, string $description): array
    {
        return $this->line($account, 0.0, $amount, false, $description);
    }

    private function debitPaymentAccount(?int $paymentAccountId, float $amount, string $description, ?int $branchId = null): array
    {
        return $this->debitAccount($this->resolvePaymentAccount($paymentAccountId, $branchId), $amount, $description);
    }

    private function creditPaymentAccount(?int $paymentAccountId, float $amount, string $description, ?int $branchId = null): array
    {
        return $this->creditAccount($this->resolvePaymentAccount($paymentAccountId, $branchId), $amount, $description);
    }

    private function resolvePaymentAccount(?int $paymentAccountId, ?int $branchId = null): ChartOfAccount
    {
        if ($paymentAccountId === null) {
            throw new \RuntimeException('Payment account is required for cash settlement.');
        }

        $account = BranchPaymentAccountService::find($paymentAccountId, $branchId);

        if ($account === null) {
            throw new \RuntimeException('Invalid payment account selected.');
        }

        return $account;
    }

    private function line(ChartOfAccount $account, float $debit, float $credit, bool $isDebit, string $description): array
    {
        return [
            'account_id' => $account->id,
            'debit' => round($debit, 2),
            'credit' => round($credit, 2),
            'decrease' => AccountPostingRules::decreaseForSide($account->type, $isDebit),
            'description' => $description,
        ];
    }
}
