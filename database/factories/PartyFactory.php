<?php

namespace Database\Factories;

use App\Models\Branch;
use App\Models\Party;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Party>
 */
class PartyFactory extends Factory
{
    protected $model = Party::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'branch_id' => Branch::factory(),
            'name' => fake()->name(),
            'phone' => fake()->numerify('01#########'),
            'email' => fake()->optional()->safeEmail(),
            'address' => fake()->optional()->address(),
        ];
    }
}
