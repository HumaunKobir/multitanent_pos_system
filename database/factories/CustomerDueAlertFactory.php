<?php

namespace Database\Factories;

use App\Enums\CustomerDueAlertStatus;
use App\Models\CustomerDueAlert;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CustomerDueAlert>
 */
class CustomerDueAlertFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'due_given_date' => fake()->dateTimeBetween('now', '+30 days')->format('Y-m-d'),
            'status' => CustomerDueAlertStatus::Unpaid,
        ];
    }
}
