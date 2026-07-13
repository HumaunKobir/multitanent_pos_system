<?php

namespace Database\Factories;

use App\Models\CustomerCoinLot;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CustomerCoinLot>
 */
class CustomerCoinLotFactory extends Factory
{
    protected $model = CustomerCoinLot::class;

    public function definition(): array
    {
        $coins = fake()->randomFloat(2, 1, 100);

        return [
            'original_coins' => $coins,
            'remaining_coins' => $coins,
            'expires_at' => now()->addMonths(6),
        ];
    }
}
