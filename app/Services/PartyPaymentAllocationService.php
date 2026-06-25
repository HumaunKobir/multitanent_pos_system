<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\CustomerPayment;
use App\Models\CustomerPaymentAllocation;
use App\Models\Purchase;
use App\Models\Sell;
use App\Models\SellPayment;
use App\Models\Supplier;
use App\Models\SupplierPayment;
use App\Models\SupplierPaymentAllocation;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class PartyPaymentAllocationService
{
    public function __construct(private InventoryAccountingService $accounting) {}

    /**
     * @return Collection<int, array{id: int, invoice_number: string, date: string|null, net_amount: float, paid_amount: float, due_amount: float}>
     */
    public function duePurchasesForSupplier(Supplier $supplier, ?SupplierPayment $editingPayment = null): Collection
    {
        $currentAllocations = $this->currentSupplierAllocationAmounts($editingPayment);

        return Purchase::query()
            ->ownBranch()
            ->purchase()
            ->where('supplier_id', $supplier->id)
            ->orderBy('date')
            ->orderBy('id')
            ->get()
            ->map(function (Purchase $purchase) use ($currentAllocations) {
                $allocatedAmount = round((float) ($currentAllocations[$purchase->id] ?? 0), 2);
                $dueAmount = round((float) $purchase->due_amount + $allocatedAmount, 2);

                return [
                    'id' => $purchase->id,
                    'invoice_number' => $purchase->invoice_number,
                    'date' => $purchase->date?->format('Y-m-d'),
                    'net_amount' => round((float) $purchase->net_amount, 2),
                    'paid_amount' => round(max(0, (float) $purchase->paid_amount - $allocatedAmount), 2),
                    'due_amount' => $dueAmount,
                    'allocated_amount' => $allocatedAmount,
                ];
            })
            ->filter(fn (array $purchase) => $purchase['due_amount'] > 0)
            ->values();
    }

    /**
     * @return Collection<int, array{id: int, invoice_number: string, date: string|null, net_amount: float, paid_amount: float, due_amount: float}>
     */
    public function dueSalesForCustomer(Customer $customer, ?CustomerPayment $editingPayment = null): Collection
    {
        $currentAllocations = $this->currentCustomerAllocationAmounts($editingPayment);

        return Sell::query()
            ->ownBranch()
            ->sale()
            ->where('customer_id', $customer->id)
            ->orderBy('date')
            ->orderBy('id')
            ->get()
            ->map(function (Sell $sell) use ($currentAllocations) {
                $allocatedAmount = round((float) ($currentAllocations[$sell->id] ?? 0), 2);
                $dueAmount = round(max(0, (float) $sell->net_amount - (float) $sell->paid_amount) + $allocatedAmount, 2);

                return [
                    'id' => $sell->id,
                    'invoice_number' => $sell->invoice_number,
                    'date' => $sell->date?->format('Y-m-d'),
                    'net_amount' => round((float) $sell->net_amount, 2),
                    'paid_amount' => round(max(0, (float) $sell->paid_amount - $allocatedAmount), 2),
                    'due_amount' => $dueAmount,
                    'allocated_amount' => $allocatedAmount,
                ];
            })
            ->filter(fn (array $sell) => $sell['due_amount'] > 0)
            ->values();
    }

    /**
     * @param  array<int, array{purchase_id: int|string, amount: float|int|string}>  $allocations
     * @return array{total: float, purchases: Collection<int, Purchase>}
     */
    public function validateSupplierAllocations(Supplier $supplier, array $allocations, ?SupplierPayment $editingPayment = null): array
    {
        if ($allocations === []) {
            throw ValidationException::withMessages([
                'allocations' => 'Select at least one purchase to pay against.',
            ]);
        }

        $purchaseIds = collect($allocations)->pluck('purchase_id')->map(fn ($id) => (int) $id);

        if ($purchaseIds->duplicates()->isNotEmpty()) {
            throw ValidationException::withMessages([
                'allocations' => 'Each purchase can only be included once.',
            ]);
        }

        $purchases = Purchase::query()
            ->ownBranch()
            ->purchase()
            ->where('supplier_id', $supplier->id)
            ->whereIn('id', $purchaseIds)
            ->get()
            ->keyBy('id');

        if ($purchases->count() !== $purchaseIds->count()) {
            throw ValidationException::withMessages([
                'allocations' => 'One or more purchases are invalid for this supplier.',
            ]);
        }

        $existingAllocations = $this->currentSupplierAllocationAmounts($editingPayment);
        $allowedBalance = round((float) $supplier->balance + (($editingPayment?->supplier_id === $supplier->id) ? (float) $editingPayment->amount : 0), 2);
        $total = 0.0;

        foreach ($allocations as $index => $allocation) {
            $purchaseId = (int) $allocation['purchase_id'];
            $amount = round((float) $allocation['amount'], 2);
            $purchase = $purchases->get($purchaseId);
            $effectiveDueAmount = round((float) $purchase->due_amount + (float) ($existingAllocations[$purchaseId] ?? 0), 2);

            if ($amount <= 0) {
                throw ValidationException::withMessages([
                    "allocations.{$index}.amount" => 'Allocation amount must be greater than zero.',
                ]);
            }

            if ($amount > $effectiveDueAmount) {
                throw ValidationException::withMessages([
                    "allocations.{$index}.amount" => "Amount exceeds due for purchase {$purchase->invoice_number}.",
                ]);
            }

            $total += $amount;
        }

        $total = round($total, 2);

        if ($total > $allowedBalance) {
            throw ValidationException::withMessages([
                'allocations' => 'Total payment cannot exceed supplier due balance.',
            ]);
        }

        return [
            'total' => $total,
            'purchases' => $purchases,
        ];
    }

    /**
     * @param  array<int, array{sell_id: int|string, amount: float|int|string}>  $allocations
     * @return array{total: float, sells: Collection<int, Sell>}
     */
    public function validateCustomerAllocations(Customer $customer, array $allocations, ?CustomerPayment $editingPayment = null): array
    {
        if ($allocations === []) {
            throw ValidationException::withMessages([
                'allocations' => 'Select at least one invoice to collect against.',
            ]);
        }

        $sellIds = collect($allocations)->pluck('sell_id')->map(fn ($id) => (int) $id);

        if ($sellIds->duplicates()->isNotEmpty()) {
            throw ValidationException::withMessages([
                'allocations' => 'Each invoice can only be included once.',
            ]);
        }

        $sells = Sell::query()
            ->ownBranch()
            ->sale()
            ->where('customer_id', $customer->id)
            ->whereIn('id', $sellIds)
            ->get()
            ->keyBy('id');

        if ($sells->count() !== $sellIds->count()) {
            throw ValidationException::withMessages([
                'allocations' => 'One or more invoices are invalid for this customer.',
            ]);
        }

        $existingAllocations = $this->currentCustomerAllocationAmounts($editingPayment);
        $allowedBalance = round((float) $customer->balance + (($editingPayment?->customer_id === $customer->id) ? (float) $editingPayment->amount : 0), 2);
        $total = 0.0;

        foreach ($allocations as $index => $allocation) {
            $sellId = (int) $allocation['sell_id'];
            $amount = round((float) $allocation['amount'], 2);
            $sell = $sells->get($sellId);
            $dueAmount = round(max(0, (float) $sell->net_amount - (float) $sell->paid_amount) + (float) ($existingAllocations[$sellId] ?? 0), 2);

            if ($amount <= 0) {
                throw ValidationException::withMessages([
                    "allocations.{$index}.amount" => 'Allocation amount must be greater than zero.',
                ]);
            }

            if ($amount > $dueAmount) {
                throw ValidationException::withMessages([
                    "allocations.{$index}.amount" => "Amount exceeds due for invoice {$sell->invoice_number}.",
                ]);
            }

            $total += $amount;
        }

        $total = round($total, 2);

        if ($total > $allowedBalance) {
            throw ValidationException::withMessages([
                'allocations' => 'Total collection cannot exceed customer due balance.',
            ]);
        }

        return [
            'total' => $total,
            'sells' => $sells,
        ];
    }

    /**
     * @param  array<int, array{purchase_id: int|string, amount: float|int|string}>  $allocations
     * @param  Collection<int, Purchase>  $purchases
     */
    public function applySupplierAllocations(SupplierPayment $payment, array $allocations, Collection $purchases): void
    {
        foreach ($allocations as $allocation) {
            $purchaseId = (int) $allocation['purchase_id'];
            $amount = round((float) $allocation['amount'], 2);
            $purchase = $purchases->get($purchaseId);

            SupplierPaymentAllocation::create([
                'supplier_payment_id' => $payment->id,
                'purchase_id' => $purchaseId,
                'amount' => $amount,
            ]);

            $purchase->increment('paid_amount', $amount);
            $purchase->decrement('due_amount', $amount);
        }
    }

    public function reverseSupplierAllocations(SupplierPayment $payment): void
    {
        $payment->loadMissing('allocations.purchase');

        foreach ($payment->allocations as $allocation) {
            $purchase = $allocation->purchase;

            if ($purchase !== null) {
                $amount = (float) $allocation->amount;
                $purchase->decrement('paid_amount', $amount);
                $purchase->increment('due_amount', $amount);
            }
        }

        $payment->allocations()->delete();
    }

    /**
     * @param  array<int, array{sell_id: int|string, amount: float|int|string}>  $allocations
     * @param  Collection<int, Sell>  $sells
     */
    public function applyCustomerAllocations(CustomerPayment $payment, array $allocations, Collection $sells): void
    {
        foreach ($allocations as $allocation) {
            $sellId = (int) $allocation['sell_id'];
            $amount = round((float) $allocation['amount'], 2);
            $sell = $sells->get($sellId);

            CustomerPaymentAllocation::create([
                'customer_payment_id' => $payment->id,
                'sell_id' => $sellId,
                'amount' => $amount,
            ]);

            $sell->increment('paid_amount', $amount);
        }
    }

    public function reverseCustomerAllocations(CustomerPayment $payment): void
    {
        $payment->loadMissing('allocations.sell');

        foreach ($payment->allocations as $allocation) {
            $sell = $allocation->sell;

            if ($sell !== null) {
                $sell->decrement('paid_amount', (float) $allocation->amount);
            }
        }

        $payment->allocations()->delete();
    }

    public function totalSupplierAllocationAmountForPurchase(Purchase $purchase): float
    {
        return round((float) SupplierPaymentAllocation::query()
            ->where('purchase_id', $purchase->id)
            ->sum('amount'), 2);
    }

    public function purchaseDirectPaidAmount(Purchase $purchase): float
    {
        return round(max(0, (float) $purchase->paid_amount - $this->totalSupplierAllocationAmountForPurchase($purchase)), 2);
    }

    /**
     * @return array{invoice_number: string, net_amount: float, paid_amount: float, due_amount: float}
     */
    public function purchasePaymentSummary(Purchase $purchase): array
    {
        return [
            'invoice_number' => $purchase->invoice_number,
            'net_amount' => round((float) $purchase->net_amount, 2),
            'paid_amount' => round((float) $purchase->paid_amount, 2),
            'due_amount' => round((float) $purchase->due_amount, 2),
        ];
    }

    /**
     * @return array{invoice_number: string, net_amount: float, paid_amount: float, due_amount: float}
     */
    public function sellPaymentSummary(Sell $sell): array
    {
        $sell->loadMissing('products');
        $netAmount = round((float) $sell->net_amount, 2);
        $paidAmount = round((float) $sell->paid_amount, 2);

        return [
            'invoice_number' => $sell->invoice_number,
            'net_amount' => $netAmount,
            'paid_amount' => $paidAmount,
            'due_amount' => round(max(0, $netAmount - $paidAmount), 2),
        ];
    }

    /**
     * @param  Collection<int, SupplierPaymentAllocation>  $allocations
     * @return array<int, array{id: int, purchase_id: int, amount: float, document: array{invoice_number: string, net_amount: float, paid_amount: float, due_amount: float}|null}>
     */
    public function mapSupplierPaymentAllocationsForView(Collection $allocations): array
    {
        return $allocations
            ->map(function (SupplierPaymentAllocation $allocation) {
                $purchase = $allocation->purchase;

                return [
                    'id' => $allocation->id,
                    'purchase_id' => (int) $allocation->purchase_id,
                    'amount' => round((float) $allocation->amount, 2),
                    'document' => $purchase !== null ? $this->purchasePaymentSummary($purchase) : null,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, CustomerPaymentAllocation>  $allocations
     * @return array<int, array{id: int, sell_id: int, amount: float, document: array{invoice_number: string, net_amount: float, paid_amount: float, due_amount: float}|null}>
     */
    public function mapCustomerPaymentAllocationsForView(Collection $allocations): array
    {
        return $allocations
            ->map(function (CustomerPaymentAllocation $allocation) {
                $sell = $allocation->sell;

                return [
                    'id' => $allocation->id,
                    'sell_id' => (int) $allocation->sell_id,
                    'amount' => round((float) $allocation->amount, 2),
                    'document' => $sell !== null ? $this->sellPaymentSummary($sell) : null,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, array{id: int, label: string}>|null  $paymentAccountLabels
     * @return array<int, array{amount: float, voucher: string, date: string|null, payment_account_id: int|null, payment_account_label: string|null}>
     */
    public function supplierAllocationDetailsForPurchase(Purchase $purchase, ?Collection $paymentAccountLabels = null): array
    {
        return SupplierPaymentAllocation::query()
            ->where('purchase_id', $purchase->id)
            ->with('supplierPayment:id,serial,date')
            ->orderBy('id')
            ->get()
            ->map(function (SupplierPaymentAllocation $allocation) use ($paymentAccountLabels) {
                $supplierPayment = $allocation->supplierPayment;
                $paymentAccountId = $supplierPayment !== null
                    ? $this->accounting->paymentAccountIdFor($supplierPayment, latest: true)
                    : null;

                return [
                    'amount' => round((float) $allocation->amount, 2),
                    'voucher' => $supplierPayment?->invoice_number ?? '',
                    'date' => $supplierPayment?->date?->format('Y-m-d'),
                    'payment_account_id' => $paymentAccountId,
                    'payment_account_label' => $this->paymentAccountLabel($paymentAccountLabels, $paymentAccountId),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, array{id: int, label: string}>|null  $paymentAccountLabels
     * @return array<int, array{amount: float, voucher: string, date: string|null, payment_account_id: int|null, payment_account_label: string|null}>
     */
    public function customerCollectionDetailsForSell(Sell $sell, ?Collection $paymentAccountLabels = null): array
    {
        return CustomerPaymentAllocation::query()
            ->where('sell_id', $sell->id)
            ->with('customerPayment:id,serial,date')
            ->orderBy('id')
            ->get()
            ->map(function (CustomerPaymentAllocation $allocation) use ($paymentAccountLabels) {
                $customerPayment = $allocation->customerPayment;
                $paymentAccountId = $customerPayment !== null
                    ? $this->accounting->paymentAccountIdFor($customerPayment, latest: true)
                    : null;

                return [
                    'amount' => round((float) $allocation->amount, 2),
                    'voucher' => $customerPayment?->invoice_number ?? '',
                    'date' => $customerPayment?->date?->format('Y-m-d'),
                    'payment_account_id' => $paymentAccountId,
                    'payment_account_label' => $this->paymentAccountLabel($paymentAccountLabels, $paymentAccountId),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, array{id: int, label: string}>|null  $paymentAccountLabels
     * @return array{amount: float, payment_account_id: int|null, payment_account_label: string|null}|null
     */
    public function purchaseDirectPaymentForView(Purchase $purchase, ?Collection $paymentAccountLabels = null): ?array
    {
        $amount = $this->purchaseDirectPaidAmount($purchase);

        if ($amount <= 0) {
            return null;
        }

        $paymentAccountId = $this->accounting->paymentAccountIdFor($purchase);

        return [
            'amount' => $amount,
            'payment_account_id' => $paymentAccountId,
            'payment_account_label' => $this->paymentAccountLabel($paymentAccountLabels, $paymentAccountId),
        ];
    }

    /**
     * @return array<int, array{payment_account_id: int, amount: float}>
     */
    public function supplierPaymentLinesForPurchase(Purchase $purchase): array
    {
        $allocations = SupplierPaymentAllocation::query()
            ->where('purchase_id', $purchase->id)
            ->with('supplierPayment')
            ->get();

        $byAccount = [];

        foreach ($allocations as $allocation) {
            $supplierPayment = $allocation->supplierPayment;

            if ($supplierPayment === null) {
                continue;
            }

            $paymentAccountId = $this->accounting->paymentAccountIdFor($supplierPayment, latest: true);

            if ($paymentAccountId === null) {
                continue;
            }

            $byAccount[$paymentAccountId] = round(
                ($byAccount[$paymentAccountId] ?? 0) + (float) $allocation->amount,
                2,
            );
        }

        return collect($byAccount)
            ->map(fn (float $amount, int $accountId) => [
                'payment_account_id' => $accountId,
                'amount' => $amount,
            ])
            ->values()
            ->all();
    }

    public function purchasePaymentAccountIdForEdit(Purchase $purchase): ?int
    {
        if ((float) $purchase->paid_amount <= 0) {
            return null;
        }

        if ($this->purchaseDirectPaidAmount($purchase) > 0) {
            $accountId = $this->accounting->paymentAccountIdFor($purchase);

            if ($accountId !== null) {
                return $accountId;
            }
        }

        $lines = $this->supplierPaymentLinesForPurchase($purchase);

        return isset($lines[0]) ? (int) $lines[0]['payment_account_id'] : null;
    }

    /**
     * @return array<int, array{payment_account_id: int, amount: float}>
     */
    public function collectionPaymentLinesForSell(Sell $sell): array
    {
        $allocations = CustomerPaymentAllocation::query()
            ->where('sell_id', $sell->id)
            ->with('customerPayment')
            ->get();

        $byAccount = [];

        foreach ($allocations as $allocation) {
            $customerPayment = $allocation->customerPayment;

            if ($customerPayment === null) {
                continue;
            }

            $paymentAccountId = $this->accounting->paymentAccountIdFor($customerPayment, latest: true);

            if ($paymentAccountId === null) {
                continue;
            }

            $byAccount[$paymentAccountId] = round(
                ($byAccount[$paymentAccountId] ?? 0) + (float) $allocation->amount,
                2,
            );
        }

        return collect($byAccount)
            ->map(fn (float $amount, int $accountId) => [
                'payment_account_id' => $accountId,
                'amount' => $amount,
            ])
            ->values()
            ->all();
    }

    public function totalCollectionAmountForSell(Sell $sell): float
    {
        return round((float) CustomerPaymentAllocation::query()
            ->where('sell_id', $sell->id)
            ->sum('amount'), 2);
    }

    /**
     * @return array<int, array{payment_account_id: int, amount: float}>
     */
    public function sellPaymentLinesForEdit(Sell $sell): array
    {
        $sell->loadMissing('payments');

        $lines = $sell->payments
            ->map(fn (SellPayment $payment) => [
                'payment_account_id' => (int) $payment->payment_account_id,
                'amount' => round((float) $payment->amount, 2),
            ])
            ->values()
            ->all();

        if ($lines !== []) {
            return $lines;
        }

        $collectionTotal = $this->totalCollectionAmountForSell($sell);
        $sellOnlyPaid = round(max(0, (float) $sell->paid_amount - $collectionTotal), 2);

        if ($sellOnlyPaid <= 0) {
            return [];
        }

        $paymentAccountId = $this->accounting->paymentAccountIdFor($sell);

        if ($paymentAccountId === null) {
            return [];
        }

        return [[
            'payment_account_id' => $paymentAccountId,
            'amount' => $sellOnlyPaid,
        ]];
    }

    /**
     * @param  Collection<int, array{id: int, label: string}>|null  $paymentAccountLabels
     */
    private function paymentAccountLabel(?Collection $paymentAccountLabels, ?int $paymentAccountId): ?string
    {
        if ($paymentAccountId === null || $paymentAccountLabels === null) {
            return null;
        }

        return $paymentAccountLabels->get($paymentAccountId)['label'] ?? null;
    }

    /**
     * @return array<int, float>
     */
    private function currentSupplierAllocationAmounts(?SupplierPayment $payment): array
    {
        if ($payment === null) {
            return [];
        }

        $payment->loadMissing('allocations');

        return $payment->allocations
            ->mapWithKeys(fn (SupplierPaymentAllocation $allocation) => [
                (int) $allocation->purchase_id => round((float) $allocation->amount, 2),
            ])
            ->all();
    }

    /**
     * @return array<int, float>
     */
    private function currentCustomerAllocationAmounts(?CustomerPayment $payment): array
    {
        if ($payment === null) {
            return [];
        }

        $payment->loadMissing('allocations');

        return $payment->allocations
            ->mapWithKeys(fn (CustomerPaymentAllocation $allocation) => [
                (int) $allocation->sell_id => round((float) $allocation->amount, 2),
            ])
            ->all();
    }
}
