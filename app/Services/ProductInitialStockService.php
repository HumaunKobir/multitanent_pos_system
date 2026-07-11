<?php

namespace App\Services;

use App\Data\InitialStockSettlement;
use App\Enums\ProductLogType;
use App\Models\Batch;
use App\Models\Product;
use App\Models\ProductInitialStock;
use App\Models\ProductInOutLog;
use App\Models\ProductVariation;
use App\Models\Supplier;
use RuntimeException;

class ProductInitialStockService
{
    private bool $skipPerRecordAccounting = false;

    public function __construct(private InventoryAccountingService $accounting) {}

    public function usingSupplierAccounting(bool $enabled, callable $callback): mixed
    {
        $previous = $this->skipPerRecordAccounting;
        $this->skipPerRecordAccounting = $enabled;

        try {
            return $callback();
        } finally {
            $this->skipPerRecordAccounting = $previous;
        }
    }

    public function syncNonVariant(Product $product, int $newQuantity, float $unitCost): void
    {
        if ($newQuantity < 0) {
            throw new RuntimeException('Initial stock cannot be negative.');
        }

        $record = ProductInitialStock::query()->firstOrNew([
            'product_id' => $product->id,
            'product_variation_id' => null,
        ]);

        $oldQuantity = (int) ($record->quantity ?? 0);
        $delta = $newQuantity - $oldQuantity;

        if ($delta === 0 && $record->exists) {
            if ((float) $record->unit_cost !== round($unitCost, 2)) {
                $record->update(['unit_cost' => $unitCost]);
            }

            return;
        }

        $batch = null;

        if ($newQuantity > 0) {
            $batch = $this->resolveBatch($record, $product, $unitCost);

            if ($delta < 0 && (float) $batch->available < abs($delta)) {
                throw new RuntimeException('Cannot reduce initial stock below the quantity already used from stock.');
            }
        } elseif ($record->batch_id !== null) {
            $batch = Batch::query()->find($record->batch_id);

            if ($batch !== null && (float) $batch->available < abs($delta)) {
                throw new RuntimeException('Cannot reduce initial stock below the quantity already used from stock.');
            }
        }

        if ($batch !== null && $delta !== 0) {
            $this->adjustBatchStock($batch, $delta);
        }

        if ($newQuantity === 0) {
            if ($record->exists) {
                if ($delta !== 0 && ! $this->skipPerRecordAccounting) {
                    $this->accounting->postProductInitialStockMovement(
                        $record,
                        round(abs($delta) * $unitCost, 2),
                        false,
                        $product->name,
                    );
                }

                $record->delete();
            }

            return;
        }

        $record->fill([
            'batch_id' => $batch?->id,
            'branch_id' => $product->branch_id,
            'quantity' => $newQuantity,
            'unit_cost' => $unitCost,
        ]);
        $record->save();

        if ($delta !== 0 && ! $this->skipPerRecordAccounting) {
            $this->accounting->postProductInitialStockMovement(
                $record,
                round(abs($delta) * $unitCost, 2),
                $delta > 0,
                $product->name,
            );
        }
    }

    public function applyVariationStockOnCreate(ProductVariation $variation, int $quantity, float $unitCost): void
    {
        if ($quantity <= 0) {
            return;
        }

        $variation->update(['stock' => $quantity]);

        $record = ProductInitialStock::query()->create([
            'product_id' => $variation->product_id,
            'product_variation_id' => $variation->id,
            'batch_id' => null,
            'branch_id' => $variation->branch_id,
            'quantity' => $quantity,
            'unit_cost' => $unitCost,
        ]);

        $variation->loadMissing('product:id,name,branch_id');
        $this->logVariationInitialStock(
            $variation->product,
            $variation,
            $quantity,
            true,
            $this->variationLabel($variation),
        );

        if ($this->skipPerRecordAccounting) {
            return;
        }

        $this->accounting->postProductInitialStockMovement(
            $record,
            round($quantity * $unitCost, 2),
            true,
            $this->variationLabel($variation),
        );
    }

    public function postStockQuantityAdjustment(
        Product $product,
        ?ProductVariation $variation,
        int $delta,
        float $unitCost,
        string $label,
    ): void {
        if ($delta === 0) {
            return;
        }

        $record = ProductInitialStock::query()->firstOrCreate(
            [
                'product_id' => $product->id,
                'product_variation_id' => $variation?->id,
            ],
            [
                'batch_id' => null,
                'branch_id' => $product->branch_id,
                'quantity' => 0,
                'unit_cost' => $unitCost,
            ],
        );

        if ((float) $record->unit_cost !== round($unitCost, 2)) {
            $record->update(['unit_cost' => $unitCost]);
        }

        if ($variation !== null) {
            $variation->refresh();
            $this->logVariationInitialStock(
                $product,
                $variation,
                abs($delta),
                $delta > 0,
                $label,
            );
        }

        if ($this->skipPerRecordAccounting) {
            return;
        }

        $this->accounting->postProductInitialStockMovement(
            $record,
            round(abs($delta) * $unitCost, 2),
            $delta > 0,
            $label,
        );
    }

    /**
     * @param  array<string, mixed>  $combo
     */
    public function resolveComboStock(array $combo, int $mainInitialStock): int
    {
        if (isset($combo['stock']) && (string) $combo['stock'] !== '') {
            return (int) $combo['stock'];
        }

        return $mainInitialStock;
    }

    public function calculateProductInitialStockValue(Product $product): float
    {
        $product->loadMissing([
            'initialStockRecord:id,product_id,quantity,unit_cost',
            'variations:id,product_id,stock,purchase_price',
        ]);

        if ($product->variations->isNotEmpty()) {
            return round($product->variations->sum(function (ProductVariation $variation): float {
                return max(0, (int) $variation->stock) * (float) $variation->purchase_price;
            }), 2);
        }

        $record = $product->initialStockRecord;

        if ($record === null) {
            return 0.0;
        }

        return round(max(0, (int) $record->quantity) * (float) $record->unit_cost, 2);
    }

    /**
     * @param  array<int, array<string, mixed>>  $combinations
     */
    public function calculateInitialStockValueFromInput(
        bool $hasVariations,
        array $combinations,
        int $mainInitialStock,
        float $mainPurchasePrice,
    ): float {
        if ($mainInitialStock < 0) {
            return 0.0;
        }

        if ($hasVariations) {
            $total = 0.0;

            foreach ($combinations as $combo) {
                $stock = $this->resolveComboStock($combo, $mainInitialStock);

                if ($stock <= 0) {
                    continue;
                }

                $purchasePrice = (isset($combo['purchase_price']) && (string) $combo['purchase_price'] !== '')
                    ? (float) $combo['purchase_price']
                    : $mainPurchasePrice;

                $total += $stock * $purchasePrice;
            }

            return round($total, 2);
        }

        if ($mainInitialStock <= 0) {
            return 0.0;
        }

        return round($mainInitialStock * $mainPurchasePrice, 2);
    }

    public function finalizeSettlement(
        Product $product,
        InitialStockSettlement $settlement,
        float $previousTotalAmount,
        ?InitialStockSettlement $previousSettlement = null,
    ): void {
        $previousSettlement ??= InitialStockSettlement::fromRequest([
            'initial_stock_supplier_id' => $product->initial_stock_supplier_id,
            'initial_stock_paid_amount' => $product->initial_stock_paid_amount,
            'initial_stock_payment_account_id' => $product->initial_stock_payment_account_id,
        ]);

        $newTotalAmount = $this->calculateProductInitialStockValue($product);

        if ($previousSettlement->usesSupplier()) {
            $previousDue = round(max(0, $previousTotalAmount - $previousSettlement->paidAmount), 2);

            if ($previousDue > 0) {
                Supplier::query()
                    ->whereKey($previousSettlement->supplierId)
                    ->decrement('balance', $previousDue);
            }

            $this->accounting->reverseFor($product);
        }

        if ($settlement->usesSupplier() && $newTotalAmount > 0) {
            if (! $previousSettlement->usesSupplier()) {
                $this->reverseAllInitialStockRecordJournals($product);
            }

            $paidAmount = round(min($settlement->paidAmount, $newTotalAmount), 2);
            $dueAmount = round(max(0, $newTotalAmount - $paidAmount), 2);

            $product->update([
                'initial_stock_supplier_id' => $settlement->supplierId,
                'initial_stock_paid_amount' => $paidAmount,
                'initial_stock_payment_account_id' => $paidAmount > 0 ? $settlement->paymentAccountId : null,
            ]);

            if ($dueAmount > 0) {
                Supplier::query()
                    ->whereKey($settlement->supplierId)
                    ->increment('balance', $dueAmount);
            }

            $product->loadMissing('initialStockSupplier:id,name');
            $supplierName = $product->initialStockSupplier?->name ?? 'Supplier';

            $this->accounting->postProductInitialStockSupplierSettlement(
                $product,
                $newTotalAmount,
                $paidAmount,
                $settlement->paymentAccountId,
                $supplierName,
            );

            return;
        }

        $this->clearSettlementFields($product);

        if ($previousSettlement->usesSupplier() && $newTotalAmount > 0) {
            $this->accounting->postProductInitialStockOpeningBalance($product, $newTotalAmount);
        }
    }

    public function clearSettlementFields(Product $product): void
    {
        if (
            $product->initial_stock_supplier_id === null
            && (float) $product->initial_stock_paid_amount === 0.0
            && $product->initial_stock_payment_account_id === null
        ) {
            return;
        }

        $product->update([
            'initial_stock_supplier_id' => null,
            'initial_stock_paid_amount' => 0,
            'initial_stock_payment_account_id' => null,
        ]);
    }

    public function reverseAccountingForDeletion(Product $product): void
    {
        $settlement = InitialStockSettlement::fromRequest([
            'initial_stock_supplier_id' => $product->initial_stock_supplier_id,
            'initial_stock_paid_amount' => $product->initial_stock_paid_amount,
            'initial_stock_payment_account_id' => $product->initial_stock_payment_account_id,
        ]);

        $totalAmount = $this->calculateProductInitialStockValue($product);

        if ($settlement->usesSupplier() && $totalAmount > 0) {
            $dueAmount = round(max(0, $totalAmount - $settlement->paidAmount), 2);

            if ($dueAmount > 0) {
                Supplier::query()
                    ->whereKey($settlement->supplierId)
                    ->decrement('balance', $dueAmount);
            }

            $this->accounting->reverseFor($product);

            return;
        }

        $this->accounting->reverseFor($product);
        $this->reverseAllInitialStockRecordJournals($product);
    }

    private function reverseAllInitialStockRecordJournals(Product $product): void
    {
        ProductInitialStock::query()
            ->where('product_id', $product->id)
            ->pluck('id')
            ->each(function (int $recordId): void {
                $record = ProductInitialStock::query()->find($recordId);

                if ($record !== null) {
                    $this->accounting->reverseFor($record);
                }
            });
    }

    private function variationLabel(ProductVariation $variation): string
    {
        $variation->loadMissing('product:id,name');

        return $variation->product->name.' — '.($variation->variation_data['label'] ?? $variation->sku);
    }

    private function resolveBatch(ProductInitialStock $record, Product $product, float $unitCost): Batch
    {
        if ($record->batch_id !== null) {
            $batch = Batch::query()->find($record->batch_id);

            if ($batch !== null) {
                if ((float) $batch->purchase_price !== round($unitCost, 2)) {
                    $batch->update(['purchase_price' => $unitCost]);
                }

                return $batch;
            }
        }

        $branchId = $product->resolveStockBranchId($product->branch_id);

        return Batch::query()->create([
            'branch_id' => $branchId,
            'product_id' => $product->id,
            'purchase_price' => $unitCost,
            'available' => 0,
        ]);
    }

    private function adjustBatchStock(Batch $batch, int $delta): void
    {
        if ($delta === 0) {
            return;
        }

        if ($delta > 0) {
            $batch->increment('available', $delta);
            $batch->refresh();
            $batch->initialStock($delta);

            return;
        }

        $amount = abs($delta);
        $batch->decrement('available', $amount);
        $batch->refresh();
        $batch->initialStock(-$amount);
    }

    private function logVariationInitialStock(
        Product $product,
        ProductVariation $variation,
        int $quantity,
        bool $increase,
        string $label,
    ): void {
        if ($quantity === 0) {
            return;
        }

        $branchId = $product->resolveStockBranchId($product->branch_id);
        $batch = Batch::query()->firstOrCreate(
            [
                'product_id' => $product->id,
                'branch_id' => $branchId,
            ],
            [
                'purchase_price' => $variation->purchase_price,
                'available' => 0,
            ],
        );

        ProductInOutLog::create([
            'batch_id' => $batch->id,
            'branch_id' => $product->branch_id,
            'product_id' => $product->id,
            'quantity' => $increase ? $quantity : -$quantity,
            'type' => ProductLogType::InitialStock->value,
            'stock' => (int) ProductVariation::query()
                ->where('product_id', $product->id)
                ->where('branch_id', $variation->branch_id)
                ->sum('stock'),
            'remark' => 'InitialStock — '.$label,
        ]);
    }
}
