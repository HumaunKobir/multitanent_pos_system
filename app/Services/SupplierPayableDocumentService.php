<?php

namespace App\Services;

use App\Enums\PurchaseType;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\Supplier;
use App\Models\SupplierPayment;
use App\Models\SupplierPaymentAllocation;
use App\Models\Transaction;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class SupplierPayableDocumentService
{
    public function __construct(
        private InventoryAccountingService $accounting,
    ) {}

    public function ensurePayableDocumentsForSupplier(Supplier $supplier): void
    {
        $this->ensureOpeningBalancePurchase($supplier);
        $this->ensureInitialStockPurchasesForSupplier($supplier);
        $this->reconcilePurchaseDueAmounts($supplier);
        $this->reconcileSupplierBalanceDocuments($supplier);
    }

    public function reconcilePurchaseDueAmounts(Supplier $supplier): void
    {
        Purchase::query()
            ->supplierPayable()
            ->where('supplier_id', $supplier->id)
            ->when($supplier->branch_id !== null, fn ($query) => $query->where(function ($inner) use ($supplier) {
                $inner->where('branch_id', $supplier->branch_id)
                    ->orWhereNull('branch_id');
            }))
            ->each(function (Purchase $purchase): void {
                $this->syncPurchaseDueAmount($purchase);
            });
    }

    /**
     * @param  iterable<int, Supplier>  $suppliers
     */
    public function reconcilePurchaseDueAmountsForSuppliers(iterable $suppliers): void
    {
        $supplierIds = collect($suppliers)->pluck('id')->map(fn ($id) => (int) $id)->filter()->unique()->values();

        if ($supplierIds->isEmpty()) {
            return;
        }

        $branchIds = collect($suppliers)
            ->pluck('branch_id')
            ->map(fn ($id) => $id !== null ? (int) $id : null)
            ->filter()
            ->unique()
            ->values();

        Purchase::query()
            ->supplierPayable()
            ->whereIn('supplier_id', $supplierIds)
            ->when($branchIds->isNotEmpty(), fn ($query) => $query->where(function ($inner) use ($branchIds) {
                $inner->whereIn('branch_id', $branchIds)
                    ->orWhereNull('branch_id');
            }))
            ->each(function (Purchase $purchase): void {
                $this->syncPurchaseDueAmount($purchase);
            });
    }

    private function syncPurchaseDueAmount(Purchase $purchase): void
    {
        $calculatedDue = round(max(0, (float) $purchase->net_amount - (float) $purchase->paid_amount), 2);
        $storedDue = round((float) $purchase->due_amount, 2);

        if (abs($calculatedDue - $storedDue) > 0.001) {
            $purchase->update(['due_amount' => $calculatedDue]);
        }
    }

    public function reconcileSupplierBalanceDocuments(Supplier $supplier): void
    {
        $supplier->refresh();

        $balance = round((float) $supplier->balance, 2);

        if ($balance <= 0) {
            return;
        }

        $documentedDue = round((float) Purchase::query()
            ->where('supplier_id', $supplier->id)
            ->when($supplier->branch_id !== null, fn ($query) => $query->where(function ($inner) use ($supplier) {
                $inner->where('branch_id', $supplier->branch_id)
                    ->orWhereNull('branch_id');
            }))
            ->supplierPayable()
            ->get()
            ->sum(fn (Purchase $purchase) => max(0, (float) $purchase->net_amount - (float) $purchase->paid_amount)), 2);

        $gap = round($balance - $documentedDue, 2);

        if ($gap <= 0) {
            return;
        }

        $purchase = Purchase::query()
            ->where('supplier_id', $supplier->id)
            ->when($supplier->branch_id !== null, fn ($query) => $query->where('branch_id', $supplier->branch_id))
            ->openingBalance()
            ->first();

        if ($purchase === null) {
            $this->createPayablePurchase([
                'branch_id' => $supplier->branch_id,
                'user_id' => Auth::id(),
                'supplier_id' => $supplier->id,
                'date' => now()->format('Y-m-d'),
                'gross_amount' => $gap,
                'discount' => 0,
                'vat' => 0,
                'paid_amount' => 0,
                'due_amount' => $gap,
                'purchase_type' => PurchaseType::OpeningBalance,
                'comment' => 'Supplier balance adjustment',
            ]);

            return;
        }

        $purchase->update([
            'gross_amount' => round((float) $purchase->gross_amount + $gap, 2),
            'due_amount' => round((float) $purchase->due_amount + $gap, 2),
        ]);
    }

    public function ensureOpeningBalancePurchase(Supplier $supplier): void
    {
        if (Purchase::query()
            ->where('supplier_id', $supplier->id)
            ->openingBalance()
            ->exists()) {
            return;
        }

        $transaction = Transaction::query()
            ->where('source_type', Supplier::class)
            ->where('source_id', $supplier->id)
            ->where('description', 'like', 'Supplier opening balance%')
            ->orderBy('id')
            ->first();

        if ($transaction === null) {
            return;
        }

        $amount = round((float) $transaction->amount, 2);

        if ($amount <= 0) {
            return;
        }

        $this->syncOpeningBalancePurchase(
            $supplier,
            $amount,
            $transaction->date->format('Y-m-d'),
        );
    }

    public function ensureInitialStockPurchasesForSupplier(Supplier $supplier): void
    {
        Product::query()
            ->where('initial_stock_supplier_id', $supplier->id)
            ->where('branch_id', $supplier->branch_id)
            ->whereDoesntHave('initialStockPayablePurchase')
            ->get(['id', 'branch_id', 'initial_stock_supplier_id', 'initial_stock_paid_amount', 'name'])
            ->each(function (Product $product) use ($supplier): void {
                $totalAmount = app(ProductInitialStockService::class)->calculateProductInitialStockValue($product);

                if ($totalAmount <= 0) {
                    return;
                }

                $paidAmount = round((float) $product->initial_stock_paid_amount, 2);

                $this->syncInitialStockPurchase(
                    $product,
                    $supplier,
                    $totalAmount,
                    $paidAmount,
                    $product->created_at?->format('Y-m-d') ?? now()->format('Y-m-d'),
                );
            });
    }

    public function syncOpeningBalancePurchase(Supplier $supplier, float $amount, string $date): Purchase
    {
        $amount = round(max(0, $amount), 2);

        $purchase = Purchase::query()
            ->where('supplier_id', $supplier->id)
            ->where('branch_id', $supplier->branch_id)
            ->openingBalance()
            ->first();

        if ($purchase === null) {
            return $this->createPayablePurchase([
                'branch_id' => $supplier->branch_id,
                'user_id' => Auth::id(),
                'supplier_id' => $supplier->id,
                'date' => $date,
                'gross_amount' => $amount,
                'discount' => 0,
                'vat' => 0,
                'paid_amount' => 0,
                'due_amount' => $amount,
                'purchase_type' => PurchaseType::OpeningBalance,
                'comment' => 'Supplier opening balance',
            ]);
        }

        $purchase->update([
            'date' => $date,
            'gross_amount' => $amount,
            'discount' => 0,
            'vat' => 0,
            'paid_amount' => 0,
            'due_amount' => $amount,
            'comment' => 'Supplier opening balance',
        ]);

        return $purchase->fresh();
    }

    public function syncInitialStockPurchase(
        Product $product,
        Supplier $supplier,
        float $totalAmount,
        float $paidAmount,
        string $date,
    ): Purchase {
        $totalAmount = round(max(0, $totalAmount), 2);
        $paidAmount = round(min(max(0, $paidAmount), $totalAmount), 2);
        $dueAmount = round(max(0, $totalAmount - $paidAmount), 2);

        $purchase = Purchase::query()
            ->where('product_id', $product->id)
            ->initialStock()
            ->first();

        $attributes = [
            'branch_id' => $product->branch_id,
            'user_id' => Auth::id(),
            'supplier_id' => $supplier->id,
            'product_id' => $product->id,
            'date' => $date,
            'gross_amount' => $totalAmount,
            'discount' => 0,
            'vat' => 0,
            'paid_amount' => $paidAmount,
            'due_amount' => $dueAmount,
            'purchase_type' => PurchaseType::InitialStock,
            'comment' => "Initial stock — {$product->name}",
        ];

        if ($purchase === null) {
            return $this->createPayablePurchase($attributes);
        }

        $purchase->update($attributes);

        return $purchase->fresh();
    }

    public function recordInitialStockSettlementPayment(
        Purchase $purchase,
        Supplier $supplier,
        float $amount,
        int $paymentAccountId,
        string $date,
    ): ?SupplierPayment {
        $amount = round(max(0, $amount), 2);

        if ($amount <= 0) {
            return null;
        }

        $this->reverseInitialStockSettlementPayment($purchase);

        $payment = SupplierPayment::create([
            'branch_id' => $purchase->branch_id,
            'supplier_id' => $supplier->id,
            'date' => $date,
            'amount' => $amount,
            'comment' => $purchase->comment,
            'created_by' => Auth::id(),
            'serial' => 'INVSP'.str_pad((string) (SupplierPayment::max('id') + 1), 8, '0', STR_PAD_LEFT),
        ]);

        SupplierPaymentAllocation::create([
            'supplier_payment_id' => $payment->id,
            'purchase_id' => $purchase->id,
            'amount' => $amount,
        ]);

        $this->accounting->postSupplierPayment($payment->fresh(['supplier']), $paymentAccountId);

        return $payment;
    }

    public function reverseInitialStockDocuments(Product $product): void
    {
        $purchase = Purchase::query()
            ->where('product_id', $product->id)
            ->initialStock()
            ->first();

        if ($purchase === null) {
            return;
        }

        $this->reverseInitialStockSettlementPayment($purchase);
        $purchase->delete();
    }

    private function reverseInitialStockSettlementPayment(Purchase $purchase): void
    {
        $payments = SupplierPayment::query()
            ->whereHas('allocations', fn ($query) => $query->where('purchase_id', $purchase->id))
            ->with('allocations')
            ->get();

        foreach ($payments as $payment) {
            foreach ($payment->allocations as $allocation) {
                $allocatedPurchase = $allocation->purchase;

                if ($allocatedPurchase !== null) {
                    $amount = (float) $allocation->amount;
                    $allocatedPurchase->decrement('paid_amount', $amount);
                    $allocatedPurchase->increment('due_amount', $amount);
                }
            }

            $payment->allocations()->delete();
            $this->accounting->reverseFor($payment);
            $payment->delete();
        }
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function createPayablePurchase(array $attributes): Purchase
    {
        try {
            return Purchase::create($attributes);
        } catch (QueryException $exception) {
            if (! $this->isMissingAutoIncrementIdError($exception)) {
                throw $exception;
            }

            return DB::transaction(function () use ($attributes): Purchase {
                $purchase = new Purchase($attributes);
                $purchase->id = (int) Purchase::query()->lockForUpdate()->max('id') + 1;
                $purchase->save();

                return $purchase->fresh();
            });
        }
    }

    private function isMissingAutoIncrementIdError(QueryException $exception): bool
    {
        return str_contains($exception->getMessage(), "Field 'id' doesn't have a default value");
    }
}
