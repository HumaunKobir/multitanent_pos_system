<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\PurchaseProduct;
use App\Models\PurchaseReturn;
use App\Models\PurchaseReturnProduct;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PurchaseReturnProduct>
 */
class PurchaseReturnProductFactory extends Factory
{
    protected $model = PurchaseReturnProduct::class;

    public function definition(): array
    {
        return [
            'branch_id' => null,
            'purchase_return_id' => PurchaseReturn::factory(),
            'purchase_product_id' => PurchaseProduct::factory(),
            'product_id' => Product::factory(),
            'variation_id' => null,
            'quantity' => 1,
            'unit_price' => 100,
            'batches' => [],
        ];
    }

    public function forPurchaseReturn(PurchaseReturn $purchaseReturn): static
    {
        return $this->state([
            'purchase_return_id' => $purchaseReturn->id,
            'branch_id' => $purchaseReturn->branch_id,
        ]);
    }
}
