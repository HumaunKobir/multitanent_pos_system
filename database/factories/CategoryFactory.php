<?php

namespace Database\Factories;

use App\Models\Branch;
use App\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Category>
 */
class CategoryFactory extends Factory
{
    public function definition(): array
    {
        return [
            'branch_id' => Branch::MAIN_BRANCH_ID,
            'name' => fake()->word(),
            'status' => 1,
        ];
    }
}
