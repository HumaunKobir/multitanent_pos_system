<?php

namespace Database\Factories;

use App\Enums\CustomerRegistrationType;
use App\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Customer>
 */
class CustomerFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->unique()->numerify('017########'),
            'password' => bcrypt('password'),
            'status' => 1,
            'registration_type' => CustomerRegistrationType::Online,
        ];
    }

    public function offline(): static
    {
        return $this->state(fn () => [
            'registration_type' => CustomerRegistrationType::Offline,
            'password' => bcrypt('12345678'),
        ]);
    }
}
