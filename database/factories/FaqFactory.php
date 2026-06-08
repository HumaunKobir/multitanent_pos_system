<?php

namespace Database\Factories;

use App\Models\Faq;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Faq>
 */
class FaqFactory extends Factory
{
    protected $model = Faq::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'question' => fake()->sentence().'?',
            'answer' => '<p>'.fake()->paragraph().'</p>',
            'sort_order' => fake()->numberBetween(0, 100),
            'status' => 1,
        ];
    }
}
