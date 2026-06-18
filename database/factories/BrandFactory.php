<?php

namespace Database\Factories;

use App\Models\Branch;
use App\Models\Brand;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Brand>
 */
class BrandFactory extends Factory
{
    protected $model = Brand::class;

    public function definition(): array
    {
        return [
            'branch_id' => Branch::MAIN_BRANCH_ID,
            'name' => fake()->unique()->company(),
            'status' => 1,
        ];
    }
}
