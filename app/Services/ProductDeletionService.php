<?php

namespace App\Services;

use App\Models\Barcode;
use App\Models\Batch;
use App\Models\Branch;
use App\Models\DamageProduct;
use App\Models\OnlineOrderProduct;
use App\Models\Product;
use App\Models\ProductExchangeProduct;
use App\Models\ProductInitialStock;
use App\Models\ProductInOutLog;
use App\Models\ProductPhoto;
use App\Models\ProductVariation;
use App\Models\PurchaseProduct;
use App\Models\PurchaseReturnProduct;
use App\Models\SaleReturnProduct;
use App\Models\SellProduct;
use App\Models\StockDistributionProduct;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class ProductDeletionService
{
    public function __construct(private ProductInitialStockService $initialStock) {}

    /**
     * @return array{action: 'deleted'|'archived', message: string}
     */
    public function delete(Product $product): array
    {
        if ($this->hasTransactionHistory($product)) {
            $stock = $this->resolveAvailableStock($product);

            if ($stock > 0) {
                throw new RuntimeException('Cannot remove product while stock remains. Reduce stock to zero first.');
            }

            $this->archive($product);

            return [
                'action' => 'archived',
                'message' => 'Product archived successfully. It was removed from the catalog but kept for purchase, sale, and return history.',
            ];
        }

        $this->hardDelete($product);

        return [
            'action' => 'deleted',
            'message' => 'Product deleted successfully.',
        ];
    }

    public function resolveAvailableStock(Product $product): float
    {
        $branchId = $product->branch_id;
        $mainBranchId = Branch::resolveMainBranchId();

        $hasVariants = ProductVariation::query()
            ->where('product_id', $product->id)
            ->exists();

        if ($hasVariants) {
            return (float) ProductVariation::query()
                ->where('product_id', $product->id)
                ->when($branchId !== null, function ($query) use ($branchId, $mainBranchId): void {
                    $query->where(function ($query) use ($branchId, $mainBranchId): void {
                        $query->where('branch_id', $branchId);

                        if ($branchId === $mainBranchId) {
                            $query->orWhereNull('branch_id');
                        }
                    });
                })
                ->sum('stock');
        }

        $query = Batch::query()->where('product_id', $product->id);

        if ($branchId !== null) {
            $query->atBranchWarehouse($branchId);
        }

        return (float) $query->sum('available');
    }

    public function hasTransactionHistory(Product $product): bool
    {
        return $this->transactionHistoryReasons($product) !== [];
    }

    /**
     * @return list<string>
     */
    public function transactionHistoryReasons(Product $product): array
    {
        $reasons = [];

        if (PurchaseProduct::query()->where('product_id', $product->id)->exists()) {
            $reasons[] = 'purchase history';
        }

        if (SellProduct::query()->where('product_id', $product->id)->exists()) {
            $reasons[] = 'sales history';
        }

        if (PurchaseReturnProduct::query()->where('product_id', $product->id)->exists()) {
            $reasons[] = 'purchase return history';
        }

        if (SaleReturnProduct::query()->where('product_id', $product->id)->exists()) {
            $reasons[] = 'sale return history';
        }

        if (DamageProduct::query()->where('product_id', $product->id)->exists()) {
            $reasons[] = 'damage history';
        }

        if (ProductExchangeProduct::query()
            ->where(function ($query) use ($product): void {
                $query->where('old_product_id', $product->id)
                    ->orWhere('new_product_id', $product->id);
            })
            ->exists()) {
            $reasons[] = 'product exchange history';
        }

        if (OnlineOrderProduct::query()->where('product_id', $product->id)->exists()) {
            $reasons[] = 'online order history';
        }

        if (StockDistributionProduct::query()->where('product_id', $product->id)->exists()) {
            $reasons[] = 'stock distribution history';
        }

        return $reasons;
    }

    private function archive(Product $product): void
    {
        DB::transaction(function () use ($product): void {
            $product->update([
                'status' => 0,
                'visible' => 'no',
            ]);
        });
    }

    private function hardDelete(Product $product): void
    {
        DB::transaction(function () use ($product): void {
            $product->loadMissing('photos');

            $this->initialStock->reverseAccountingForDeletion($product);

            ProductInOutLog::query()->where('product_id', $product->id)->delete();
            ProductInitialStock::query()->where('product_id', $product->id)->delete();
            Batch::query()->where('product_id', $product->id)->delete();
            Barcode::query()->where('product_id', $product->id)->delete();
            ProductVariation::query()->where('product_id', $product->id)->delete();

            foreach ($product->photos as $photo) {
                Storage::disk('public')->delete($photo->image);
            }

            ProductPhoto::query()->where('product_id', $product->id)->delete();

            if ($product->image) {
                Storage::disk('public')->delete($product->image);
            }

            if ($product->chest_size_image) {
                Storage::disk('public')->delete($product->chest_size_image);
            }

            $product->delete();
        });
    }
}
