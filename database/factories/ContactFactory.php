<?php

namespace Database\Factories;

use App\Models\Contact;
use App\Services\EcommerceBranchService;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Contact>
 */
class ContactFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'branch_id' => app(EcommerceBranchService::class)->resolveId(),
            'name' => fake()->name(),
            'email' => fake()->optional()->safeEmail(),
            'phone' => fake()->optional()->numerify('01#########'),
            'message' => fake()->paragraph(),
        ];
    }
}
