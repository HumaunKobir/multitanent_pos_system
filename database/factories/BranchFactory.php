<?php

namespace Database\Factories;

use App\Enums\CommonStatus;
use App\Models\Branch;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Branch>
 */
class BranchFactory extends Factory
{
    protected $model = Branch::class;

    public function definition(): array
    {
        return [
            'name' => fake()->company(),
            'phone' => fake()->numerify('01#########'),
            'address' => fake()->address(),
            'status' => CommonStatus::Active,
        ];
    }
}
