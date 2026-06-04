<?php

namespace Database\Factories;

use App\Enums\CommonStatus;
use App\Models\Branch;
use App\Models\Supplier;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Supplier>
 */
class SupplierFactory extends Factory
{
    protected $model = Supplier::class;

    public function definition(): array
    {
        return [
            'branch_id' => Branch::factory(),
            'name' => fake()->name(),
            'phone' => fake()->unique()->numerify('017########'),
            'company_name' => fake()->optional()->company(),
            'address' => fake()->optional()->address(),
            'balance' => 0,
            'status' => CommonStatus::Active,
        ];
    }
}
