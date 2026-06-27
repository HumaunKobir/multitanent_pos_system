<?php

namespace App\Services;

use App\Enums\AccountType;
use App\Enums\CommonStatus;
use App\Enums\PurchaseReceivedPayment;
use App\Enums\ReceivedPaymentMethod;
use App\Enums\SystemAccountKey;
use App\Models\Branch;
use App\Models\ChartOfAccount;
use App\Models\Customer;
use App\Models\CustomerPayment;
use App\Models\Damage;
use App\Models\Ledger;
use App\Models\OnlineOrder;
use App\Models\ProductExchange;
use App\Models\ProductInitialStock;
use App\Models\Purchase;
use App\Models\PurchaseReturn;
use App\Models\SaleReturn;
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

    public function postPurchaseReturn(PurchaseReturn $purchaseReturn): ?Transaction
    {
        $purchaseReturn->loadMissing('supplier:id,name');

        $returnBase = round(max(0, (float) $purchaseReturn->gross_amount - (float) $purchaseReturn->discount), 2);
        $vatAmount = round((float) $purchaseReturn->vat, 2);
        $returnNet = round((float) $purchaseReturn->net_amount, 2);
        $serial = $purchaseReturn->serial ?? $purchaseReturn->invoice_number;
        $supplierName = $purchaseReturn->supplier?->name ?? 'Supplier';
        $branchId = $purchaseReturn->branch_id;

        $lines = [];

        if ($purchaseReturn->payment_type === PurchaseReceivedPayment::Cash && $returnNet > 0) {
            $lines[] = $this->debitLine(SystemAccountKey::CashInHand, $returnNet, "Cash received — Purchase Return {$serial}", $branchId);
        } elseif ($returnNet > 0) {
            $lines[] = $this->debitLine(SystemAccountKey::SupplierPayables, $returnNet, "Supplier payable reduced — Purchase Return {$serial}, {$supplierName}", $branchId);
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
            $lines[] = $this->debitLine(SystemAccountKey::CustomerReceivables, $dueAmount, "Receivable — Sale {$invoice}, {$customerName}");
        }

        if ($salesBase > 0) {
            $lines[] = $this->creditLine(SystemAccountKey::ProductSales, $salesBase, "Sales revenue — Sale {$invoice}");
        }

        if ($vatAmount > 0) {
            $lines[] = $this->creditLine(SystemAccountKey::OutputVat, $vatAmount, "Output VAT — Sale {$invoice}");
        }

        if ($cogs > 0) {
            $lines[] = $this->debitLine(SystemAccountKey::CostOfGoodsSold, $cogs, "COGS — Sale {$invoice}");
            $lines[] = $this->creditLine(SystemAccountKey::ProductInventory, $cogs, "Inventory reduced — Sale {$invoice}");
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

    public function postSaleReturn(SaleReturn $saleReturn, array $paymentLines, float $returnCost): Transaction
    {
        $saleReturn->loadMissing(['customer:id,name']);

        $returnNet = round((float) $saleReturn->net_amount, 2);
        $paidAmount = round((float) $saleReturn->paid_amount, 2);
        $returnVat = round((float) $saleReturn->vat_amount, 2);
        $returnBase = round($returnNet - $returnVat, 2);

        $invoice = $saleReturn->invoice_number;
        $customerName = $saleReturn->customer?->name ?? 'Customer';

        $lines = [];

        if ($returnBase > 0) {
            $lines[] = $this->debitLine(SystemAccountKey::SalesReturns, $returnBase, "Sales return — {$invoice}, {$customerName}");
        }

        if ($returnVat > 0) {
            $lines[] = $this->debitLine(SystemAccountKey::OutputVat, $returnVat, "Output VAT reversed — {$invoice}");
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
            );
            $cashCredit = round($cashCredit + $lineAmount, 2);
        }

        if ($saleReturn->payment_type === ReceivedPaymentMethod::Customer_Account && $paidAmount > 0) {
            $arCredit = $paidAmount;
        }

        $remainingCredit = round(max(0, $returnNet - $cashCredit - $arCredit), 2);

        if ($remainingCredit > 0 && $saleReturn->customer_id) {
            $arCredit = round($arCredit + $remainingCredit, 2);
        } elseif ($remainingCredit > 0 && $cashCredit <= 0) {
            $fallbackAccountId = $paymentLines[0]['payment_account_id'] ?? null;
            if ($fallbackAccountId) {
                $lines[] = $this->creditPaymentAccount(
                    (int) $fallbackAccountId,
                    $remainingCredit,
                    "Cash refunded — Sale Return {$invoice}",
                );
                $cashCredit = round($cashCredit + $remainingCredit, 2);
            }
        }

        if ($arCredit > 0) {
            $lines[] = $this->creditLine(SystemAccountKey::CustomerReceivables, $arCredit, "Receivable reduced — Sale Return {$invoice}, {$customerName}");
        }

        if ($returnCost > 0) {
            $lines[] = $this->debitLine(SystemAccountKey::ProductInventory, $returnCost, "Inventory restored — Sale Return {$invoice}");
            $lines[] = $this->creditLine(SystemAccountKey::CostOfGoodsSold, $returnCost, "COGS reversed — Sale Return {$invoice}");
        }

        return $this->postJournal(
            SaleReturn::class,
            $saleReturn->id,
            $saleReturn->date->format('Y-m-d'),
            "Sale Return {$invoice}",
            $lines,
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
            $this->creditPaymentAccount($paymentAccountId, $amount, "Cash paid — Payment {$serial}"),
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

        if ($exchange->payment_type === ReceivedPaymentMethod::Customer_Account) {
            if ($priceDifference > 0) {
                $lines[] = $this->debitLine(SystemAccountKey::CustomerReceivables, $priceDifference, "Receivable — Exchange {$invoice}, {$customerName}");
            } elseif ($priceDifference < 0) {
                $lines[] = $this->creditLine(SystemAccountKey::CustomerReceivables, abs($priceDifference), "Receivable reduced — Exchange {$invoice}, {$customerName}");
            }
        } else {
            if ($priceDifference > 0) {
                $lines[] = $this->debitPaymentAccount($paymentAccountId, $priceDifference, "Cash received — Exchange {$invoice}");
            } elseif ($priceDifference < 0) {
                $lines[] = $this->creditPaymentAccount($paymentAccountId, abs($priceDifference), "Cash refunded — Exchange {$invoice}");
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
     * @return array{
     *   old_base: float,
     *   old_vat: float,
     *   new_base: float,
     *   new_vat: float,
     *   old_cost: float,
     *   new_cost: float,
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
            $oldGross += (float) $line->old_quantity * (float) $line->old_unit_price;
            $newGross += (float) $line->new_quantity * (float) $line->new_unit_price;
            $oldCost += $this->costService->costForLine(
                $line->old_variation_id ? (int) $line->old_variation_id : null,
                (float) $line->old_quantity,
                $line->old_batches ?? [],
            );
            $newCost += $this->costService->costForLine(
                $line->new_variation_id ? (int) $line->new_variation_id : null,
                (float) $line->new_quantity,
                $line->new_batches ?? [],
            );
        }

        [$oldBase, $oldVat] = $this->splitVat($oldGross, $vatRatio);
        $newGrossNet = max(0, $newGross - (float) $exchange->special_discount_amount);
        [$newBase, $newVat] = $this->splitVat($newGrossNet, $vatRatio);

        return [
            'old_base' => $oldBase,
            'old_vat' => $oldVat,
            'new_base' => $newBase,
            'new_vat' => $newVat,
            'old_cost' => round($oldCost, 2),
            'new_cost' => round($newCost, 2),
            'paid_amount' => (float) $exchange->paid_amount,
            'price_difference' => (float) $exchange->price_difference,
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

        $lines = match ($account->type) {
            AccountType::Asset => [
                $this->debitAccount($account, $amount, "Opening balance — {$account->name}"),
                $this->creditLine($equity, $amount, "Opening balance offset — {$account->name}"),
            ],
            AccountType::Liability, AccountType::Income => [
                $this->debitLine($equity, $amount, "Opening balance offset — {$account->name}"),
                $this->creditAccount($account, $amount, "Opening balance — {$account->name}"),
            ],
            AccountType::Equity => [
                $this->debitLine($equity, $amount, "Opening balance offset — {$account->name}"),
                $this->creditAccount($account, $amount, "Opening balance — {$account->name}"),
            ],
            AccountType::Expenses => [
                $this->debitAccount($account, $amount, "Opening balance — {$account->name}"),
                $this->creditLine($equity, $amount, "Opening balance offset — {$account->name}"),
            ],
        };

        return $this->postJournal(
            ChartOfAccount::class,
            $account->id,
            $date,
            "Opening balance — {$account->name}",
            $lines,
            validateBalance: false,
        );
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

        return $this->postJournal(
            Supplier::class,
            $supplier->id,
            $date,
            "Supplier opening balance — {$supplier->name}",
            $lines,
            validateBalance: false,
        );
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

        return $this->postJournal(
            Customer::class,
            $customer->id,
            $date,
            "Customer opening balance — {$customer->name}",
            $lines,
            validateBalance: false,
        );
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

        $lines = $increase
            ? [
                $this->debitLine(SystemAccountKey::ProductInventory, $amount, "Initial stock {$direction} — {$productLabel}", $branchId),
                $this->creditLine(SystemAccountKey::OpeningBalanceClearing, $amount, "Opening balance offset — Initial stock {$productLabel}", $branchId),
            ]
            : [
                $this->debitLine(SystemAccountKey::OpeningBalanceClearing, $amount, "Opening balance offset — Initial stock {$productLabel}", $branchId),
                $this->creditLine(SystemAccountKey::ProductInventory, $amount, "Initial stock {$direction} — {$productLabel}", $branchId),
            ];

        return $this->postJournal(
            ProductInitialStock::class,
            $record->id ?? 0,
            now()->format('Y-m-d'),
            "Product initial stock — {$productLabel}",
            $lines,
            validateBalance: $increase,
        );
    }

    public function reverseFor(Model $source): void
    {
        $transaction = $this->findTransactionFor($source);

        if ($transaction !== null) {
            TransactionService::reverseTransaction($transaction);
        }
    }

    public function findTransactionFor(Model $source): ?Transaction
    {
        return Transaction::query()
            ->where('source_type', $source::class)
            ->where('source_id', $source->getKey())
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

        $query = ChartOfAccount::query()->whereKey($paymentAccountId);

        if ($branchId !== null) {
            $cashAndBankId = SystemAccountService::id(SystemAccountKey::CashAndBank, $branchId);

            $query
                ->where('source_type', Branch::class)
                ->where('source_id', $branchId)
                ->where('parent_id', $cashAndBankId)
                ->where('type', AccountType::Asset)
                ->where('status', CommonStatus::Active);
        } else {
            $query->paymentAccount();
        }

        $account = $query->first();

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
