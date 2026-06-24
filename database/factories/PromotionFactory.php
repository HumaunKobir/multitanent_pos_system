<?php

namespace Database\Factories;

use App\Enums\PromotionScope;
use App\Enums\PromotionType;
use App\Models\Promotion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Promotion>
 */
class PromotionFactory extends Factory
{
    protected $model = Promotion::class;

    public function definition(): array
    {
        return [
            'branch_id' => null,
            'name' => fake()->words(2, true),
            'description' => null,
            'scope' => PromotionScope::Product,
            'type' => PromotionType::Percent,
            'discount_value' => 10,
            'fixed_price' => null,
            'buy_qty' => null,
            'get_qty' => null,
            'get_discount_percent' => null,
            'bundle_product_ids' => null,
            'min_qty' => null,
            'starts_at' => null,
            'ends_at' => null,
            'status' => true,
            'priority' => 0,
            'stack_with_product_discount' => true,
            'stack_with_manual_line_discount' => true,
            'stack_with_invoice_discount' => true,
            'stack_with_special_discount' => true,
            'exclusive' => false,
        ];
    }

    public function percent(float $value = 10): static
    {
        return $this->state(fn () => [
            'type' => PromotionType::Percent,
            'discount_value' => $value,
        ]);
    }

    public function flat(float $value = 50): static
    {
        return $this->state(fn () => [
            'type' => PromotionType::Flat,
            'discount_value' => $value,
        ]);
    }

    public function fixedPrice(float $price = 99): static
    {
        return $this->state(fn () => [
            'type' => PromotionType::FixedPrice,
            'fixed_price' => $price,
        ]);
    }

    public function forCategory(): static
    {
        return $this->state(fn () => ['scope' => PromotionScope::Category]);
    }

    public function forBrand(): static
    {
        return $this->state(fn () => ['scope' => PromotionScope::Brand]);
    }

    public function forProduct(): static
    {
        return $this->state(fn () => ['scope' => PromotionScope::Product]);
    }

    public function withMinQty(float $minQty): static
    {
        return $this->state(fn () => ['min_qty' => $minQty]);
    }
}
