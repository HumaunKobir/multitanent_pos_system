<?php

namespace Database\Factories;

use App\Enums\DiscountType;
use App\Models\SpecialDiscount;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SpecialDiscount>
 */
class SpecialDiscountFactory extends Factory
{
    protected $model = SpecialDiscount::class;

    public function definition(): array
    {
        return [
            'branch_id' => null,
            'name' => fake()->words(2, true),
            'min_amount' => 1000,
            'max_amount' => null,
            'discount_type' => DiscountType::Flat,
            'discount_value' => 100,
            'status' => true,
        ];
    }

    public function percent(float $value = 10): static
    {
        return $this->state(fn () => [
            'discount_type' => DiscountType::Percent,
            'discount_value' => $value,
        ]);
    }

    public function range(float $min, ?float $max = null): static
    {
        return $this->state(fn () => [
            'min_amount' => $min,
            'max_amount' => $max,
        ]);
    }
}
