<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->words(3, true);

        return [
            'category_id' => Category::factory(),
            'name' => $name,
            'slug' => Str::slug($name).'-'.fake()->unique()->numerify('###'),
            'sale_price' => fake()->numberBetween(500, 3000),
            'discount_price' => 0,
            'purchase_price' => fake()->numberBetween(300, 499),
            'colors' => ['Red', 'Blue'],
            'sizes' => ['M', 'L'],
            'tags' => ['Casual'],
            'visible' => 'yes',
            'status' => 1,
        ];
    }

    public function inactive(): static
    {
        return $this->state(['status' => 0]);
    }

    public function hidden(): static
    {
        return $this->state(['visible' => 'no']);
    }
}
