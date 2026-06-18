<?php

namespace App\Services;

use App\Models\Batch;
use App\Models\Product;
use App\Models\ProductInitialStock;
use App\Models\ProductVariation;
use RuntimeException;

class ProductInitialStockService
{
    public function __construct(private InventoryAccountingService $accounting) {}

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
                if ($delta !== 0) {
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

        if ($delta !== 0) {
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

        $variation->loadMissing('product:id,name');

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
}
