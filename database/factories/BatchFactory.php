<?php

namespace Database\Factories;

use App\Models\Batch;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Batch>
 */
class BatchFactory extends Factory
{
    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'branch_id' => null,
            'supplier_id' => null,
            'purchase_price' => fake()->numberBetween(100, 500),
            'available' => fake()->numberBetween(10, 100),
            'expiry_date' => null,
            'serial' => null,
        ];
    }

    public function withStock(float $available): static
    {
        return $this->state(['available' => $available]);
    }

    public function outOfStock(): static
    {
        return $this->state(['available' => 0]);
    }
}
