<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\ProductReview;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductReview>
 */
class ProductReviewFactory extends Factory
{
    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'reviewer_name' => fake()->name(),
            'rating' => fake()->numberBetween(3, 5),
            'comment' => fake()->paragraph(),
            'status' => 1,
        ];
    }

    public function pending(): static
    {
        return $this->state(['status' => 0]);
    }
}
