<?php

namespace Database\Factories;

use App\Models\Sell;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Sell>
 */
class SellFactory extends Factory
{
    public function definition(): array
    {
        return [
            'branch_id' => null,
            'customer_id' => null,
            'date' => fake()->dateTimeBetween('-30 days', 'now')->format('Y-m-d'),
            'gross_amount' => fake()->numberBetween(500, 5000),
            'discount' => 0,
            'vat' => 0,
            'paid_amount' => fake()->numberBetween(0, 500),
            'comment' => null,
        ];
    }
}
