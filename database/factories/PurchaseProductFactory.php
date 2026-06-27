<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\Purchase;
use App\Models\PurchaseProduct;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PurchaseProduct>
 */
class PurchaseProductFactory extends Factory
{
    protected $model = PurchaseProduct::class;

    public function definition(): array
    {
        return [
            'branch_id' => null,
            'purchase_id' => Purchase::factory(),
            'product_id' => Product::factory(),
            'variation_id' => null,
            'quantity' => 1,
            'unit_price' => 100,
            'serial' => null,
            'batches' => [],
        ];
    }

    public function forPurchase(Purchase $purchase): static
    {
        return $this->state([
            'purchase_id' => $purchase->id,
            'branch_id' => $purchase->branch_id,
        ]);
    }

    public function forProduct(Product $product): static
    {
        return $this->state([
            'product_id' => $product->id,
            'branch_id' => $product->branch_id,
        ]);
    }

    public function withBatch(int $batchId, float $quantity): static
    {
        return $this->state([
            'quantity' => $quantity,
            'batches' => [(string) $batchId => $quantity],
        ]);
    }
}
