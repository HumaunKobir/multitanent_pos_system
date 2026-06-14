<?php

namespace App\Services;

use App\Enums\DiscountType;
use App\Models\SpecialDiscount;

class SpecialDiscountService
{
    public function computeAmount(DiscountType $type, float $value, float $base): float
    {
        if ($base <= 0 || $value <= 0) {
            return 0.0;
        }

        $amount = match ($type) {
            DiscountType::Percent => $base * $value / 100,
            DiscountType::Flat => $value,
        };

        return round(min($amount, $base), 2);
    }

    public function findBestMatch(float $amount, ?int $branchId): ?SpecialDiscount
    {
        if ($amount <= 0) {
            return null;
        }

        return SpecialDiscount::query()
            ->active()
            ->when($branchId, fn ($query) => $query->accessibleAtBranch($branchId))
            ->where('min_amount', '<=', $amount)
            ->where(function ($query) use ($amount) {
                $query->whereNull('max_amount')
                    ->orWhere('max_amount', '>=', $amount);
            })
            ->orderByDesc('min_amount')
            ->first();
    }

    public function resolveForSale(?int $specialDiscountId, float $amount, ?int $branchId): ?array
    {
        if ($amount <= 0) {
            return null;
        }

        $discount = null;

        if ($specialDiscountId !== null) {
            $discount = SpecialDiscount::query()
                ->active()
                ->when($branchId, fn ($query) => $query->accessibleAtBranch($branchId))
                ->find($specialDiscountId);
        } else {
            $discount = $this->findBestMatch($amount, $branchId);
        }

        if (! $discount || ! $discount->appliesToAmount($amount)) {
            return null;
        }

        return [
            'id' => $discount->id,
            'name' => $discount->name,
            'amount' => $this->computeAmount(
                $discount->discount_type,
                (float) $discount->discount_value,
                $amount,
            ),
            'discount_type' => $discount->discount_type->value,
            'discount_value' => (float) $discount->discount_value,
        ];
    }

    /**
     * @return array<int, array{
     *   id: int,
     *   name: string,
     *   min_amount: float,
     *   max_amount: ?float,
     *   discount_type: string,
     *   discount_value: float
     * }>
     */
    public function activeForBranch(?int $branchId): array
    {
        return SpecialDiscount::query()
            ->active()
            ->when($branchId, fn ($query) => $query->accessibleAtBranch($branchId))
            ->orderBy('min_amount')
            ->get(['id', 'name', 'min_amount', 'max_amount', 'discount_type', 'discount_value'])
            ->map(fn (SpecialDiscount $discount) => [
                'id' => $discount->id,
                'name' => $discount->name,
                'min_amount' => (float) $discount->min_amount,
                'max_amount' => $discount->max_amount !== null ? (float) $discount->max_amount : null,
                'discount_type' => $discount->discount_type->value,
                'discount_value' => (float) $discount->discount_value,
            ])
            ->values()
            ->all();
    }
}
