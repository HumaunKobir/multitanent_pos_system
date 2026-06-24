<?php

namespace App\Services;

use App\Enums\PromotionScope;
use App\Enums\PromotionType;
use App\Models\Product;
use App\Models\ProductVariation;
use App\Models\Promotion;
use Illuminate\Support\Collection;

class PromotionService
{
    /** @var array<string, Collection<int, Promotion>> */
    private array $cachedPromotions = [];

    /**
     * @return array<int, array<string, mixed>>
     */
    public function activeForBranch(?int $branchId): array
    {
        return Promotion::query()
            ->with('targets')
            ->active()
            ->where(function ($query) {
                $query->whereNull('ends_at')
                    ->orWhere('ends_at', '>=', now());
            })
            ->when($branchId, fn ($query) => $query->accessibleAtBranch($branchId))
            ->orderByDesc('priority')
            ->get()
            ->map(fn (Promotion $promotion) => $this->serializePromotion($promotion))
            ->values()
            ->all();
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @return array{
     *   items: array<int, array<string, mixed>>,
     *   promotion_discount_total: float,
     *   stacking: array<string, bool>
     * }
     */
    public function applyToCart(array $items, ?int $branchId, ?string $saleDate = null): array
    {
        $promotions = $this->loadPromotions($branchId, $saleDate);
        $productMap = $this->loadProductsForItems($items, $branchId);
        $items = $this->prepareItemsForPromotion($items);

        $resolved = $this->applyLinePromotions($items, $promotions, $productMap);
        $resolved = $this->applyBuyXGetY($resolved['items'], $promotions, $productMap);
        $resolved = $this->applyBundlePromotions($resolved['items'], $promotions, $productMap);

        $promotionDiscountTotal = round(collect($resolved['items'])->sum(fn (array $item) => (float) ($item['promotion_discount'] ?? 0)), 2);
        $stacking = $this->resolveStackingFlags($resolved['items'], $promotions);

        return [
            'items' => $resolved['items'],
            'promotion_discount_total' => $promotionDiscountTotal,
            'stacking' => $stacking,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @return array{
     *   items: array<int, array<string, mixed>>,
     *   promotion_discount_total: float,
     *   stacking: array<string, bool>
     * }
     */
    public function validateAndResolve(array $items, ?int $branchId, ?string $saleDate = null): array
    {
        return $this->applyToCart($items, $branchId, $saleDate);
    }

    public function matchesProduct(Promotion $promotion, Product $product): bool
    {
        $targetIds = $promotion->targets
            ->where('target_type', $promotion->scope->value)
            ->pluck('target_id')
            ->map(fn ($id) => (int) $id);

        if ($targetIds->isEmpty()) {
            return false;
        }

        return match ($promotion->scope) {
            PromotionScope::Product => $targetIds->contains((int) $product->id),
            PromotionScope::Category => $product->category_id !== null && $targetIds->contains((int) $product->category_id),
            PromotionScope::Brand => $product->brand_id !== null && $targetIds->contains((int) $product->brand_id),
        };
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @param  Collection<int, Promotion>  $promotions
     * @param  Collection<int, Product>  $productMap
     * @return array{items: array<int, array<string, mixed>>}
     */
    private function applyLinePromotions(array $items, Collection $promotions, Collection $productMap): array
    {
        $linePromotions = $promotions->filter(fn (Promotion $promotion) => in_array($promotion->type, [
            PromotionType::Percent,
            PromotionType::Flat,
            PromotionType::FixedPrice,
        ], true));

        $resolvedItems = [];

        foreach ($items as $item) {
            $product = $productMap->get((int) $item['product_id']);
            $paidQty = (float) $item['quantity'];
            $totalQty = $this->linePhysicalQuantity($item);
            $variationId = ! empty($item['variation_id']) ? (int) $item['variation_id'] : null;

            [$basePrice, $catalogPrice] = $this->resolveCatalogPrices($product, $variationId);
            $promotion = $this->selectPromotionForLine($linePromotions, $product, $totalQty);

            $line = array_merge($item, [
                'original_unit_price' => $catalogPrice,
                'unit_price' => $catalogPrice,
                'promotion_id' => null,
                'promotion_discount' => 0.0,
                'promotion_meta' => null,
                'promotion_label' => null,
            ]);

            if ($promotion !== null && $product !== null) {
                $pricingBase = $promotion->stack_with_product_discount
                    ? $catalogPrice
                    : $basePrice;

                $promoUnitPrice = $this->computePromoUnitPrice($promotion, $pricingBase, $paidQty);
                $finalUnitPrice = $promotion->stack_with_product_discount
                    ? min($catalogPrice, $promoUnitPrice)
                    : min($catalogPrice, $promoUnitPrice);

                $promotionDiscount = round(max(0, ($catalogPrice - $finalUnitPrice) * $paidQty), 2);

                $line['unit_price'] = $finalUnitPrice;
                $line['promotion_id'] = $promotion->id;
                $line['promotion_discount'] = $promotionDiscount;
                $line['promotion_label'] = $promotion->name;
                $line['promotion_meta'] = [
                    'type' => $promotion->type->value,
                    'stack_with_manual_line_discount' => $promotion->stack_with_manual_line_discount,
                    'stack_with_invoice_discount' => $promotion->stack_with_invoice_discount,
                    'stack_with_special_discount' => $promotion->stack_with_special_discount,
                ];
            }

            $resolvedItems[] = $line;
        }

        return ['items' => $resolvedItems];
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @param  Collection<int, Promotion>  $promotions
     * @param  Collection<int, Product>  $productMap
     * @return array{items: array<int, array<string, mixed>>}
     */
    private function applyBuyXGetY(array $items, Collection $promotions, Collection $productMap): array
    {
        $bogoPromotions = $promotions->filter(fn (Promotion $promotion) => $promotion->type === PromotionType::BuyXGetY);

        if ($bogoPromotions->isEmpty()) {
            return ['items' => $items];
        }

        foreach ($items as $index => $item) {
            $product = $productMap->get((int) $item['product_id']);
            $paidQty = (float) $item['quantity'];

            $promotion = $this->selectBogoPromotionForLine($bogoPromotions, $product, $paidQty);

            if ($promotion === null) {
                continue;
            }

            if (! empty($item['promotion_id']) && ($item['promotion_meta']['type'] ?? null) !== PromotionType::BuyXGetY->value) {
                $existingPromotion = $promotions->firstWhere('id', (int) $item['promotion_id']);

                if ($existingPromotion !== null && $existingPromotion->priority >= $promotion->priority) {
                    continue;
                }
            }

            $buyQty = (int) ($promotion->buy_qty ?? 0);
            $getQty = (int) ($promotion->get_qty ?? 0);
            $freeUnits = $this->calculateBogoFreeUnits((int) $paidQty, $buyQty, $getQty);

            if ($freeUnits <= 0) {
                continue;
            }

            $unitPrice = (float) $item['unit_price'];
            $discountPercent = (float) ($promotion->get_discount_percent ?? 100);
            $partialFreeDiscount = round($unitPrice * $freeUnits * max(0, (100 - $discountPercent) / 100), 2);

            $items[$index]['free_quantity'] = (float) $freeUnits;
            $items[$index]['promotion_id'] = $promotion->id;
            $items[$index]['promotion_discount'] = round((float) ($items[$index]['promotion_discount'] ?? 0) + $partialFreeDiscount, 2);
            $items[$index]['promotion_label'] = $promotion->name;
            $items[$index]['promotion_meta'] = array_merge($items[$index]['promotion_meta'] ?? [], [
                'type' => PromotionType::BuyXGetY->value,
                'free_units' => $freeUnits,
                'paid_units' => $paidQty,
                'stack_with_manual_line_discount' => $promotion->stack_with_manual_line_discount,
                'stack_with_invoice_discount' => $promotion->stack_with_invoice_discount,
                'stack_with_special_discount' => $promotion->stack_with_special_discount,
            ]);
        }

        return ['items' => $items];
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @param  Collection<int, Promotion>  $promotions
     * @param  Collection<int, Product>  $productMap
     * @return array{items: array<int, array<string, mixed>>}
     */
    private function applyBundlePromotions(array $items, Collection $promotions, Collection $productMap): array
    {
        $bundlePromotions = $promotions->filter(fn (Promotion $promotion) => $promotion->type === PromotionType::Bundle);

        foreach ($bundlePromotions as $promotion) {
            $requiredIds = collect($promotion->bundle_product_ids ?? [])
                ->map(fn ($id) => (int) $id)
                ->filter()
                ->values();

            if ($requiredIds->isEmpty()) {
                continue;
            }

            $cartProductIds = collect($items)->pluck('product_id')->map(fn ($id) => (int) $id);
            if ($requiredIds->diff($cartProductIds)->isNotEmpty()) {
                continue;
            }

            $bundleLines = collect($items)->filter(fn (array $item) => $requiredIds->contains((int) $item['product_id']));
            $bundleGross = $bundleLines->sum(fn (array $item) => (float) $item['quantity'] * (float) $item['unit_price']);

            if ($bundleGross <= 0) {
                continue;
            }

            $bundleDiscount = match ($promotion->type) {
                PromotionType::Bundle => $this->computeBundleDiscountAmount($promotion, $bundleGross),
                default => 0.0,
            };

            if ($bundleDiscount <= 0) {
                continue;
            }

            $remainingDiscount = $bundleDiscount;

            foreach ($items as $index => $item) {
                if (! $requiredIds->contains((int) $item['product_id'])) {
                    continue;
                }

                $lineGross = (float) $item['quantity'] * (float) $item['unit_price'];
                $share = $bundleGross > 0 ? ($lineGross / $bundleGross) : 0;
                $lineDiscount = round(min($remainingDiscount, $bundleDiscount * $share), 2);
                $remainingDiscount = round($remainingDiscount - $lineDiscount, 2);

                $items[$index]['promotion_id'] = $promotion->id;
                $items[$index]['promotion_discount'] = round((float) ($items[$index]['promotion_discount'] ?? 0) + $lineDiscount, 2);
                $items[$index]['promotion_label'] = $promotion->name;
                $items[$index]['promotion_meta'] = array_merge($items[$index]['promotion_meta'] ?? [], [
                    'type' => PromotionType::Bundle->value,
                    'bundle_id' => $promotion->id,
                    'stack_with_manual_line_discount' => $promotion->stack_with_manual_line_discount,
                    'stack_with_invoice_discount' => $promotion->stack_with_invoice_discount,
                    'stack_with_special_discount' => $promotion->stack_with_special_discount,
                ]);
            }
        }

        return ['items' => $items];
    }

    private function computeBundleDiscountAmount(Promotion $promotion, float $bundleGross): float
    {
        if ($promotion->fixed_price !== null && (float) $promotion->fixed_price >= 0) {
            return round(max(0, $bundleGross - (float) $promotion->fixed_price), 2);
        }

        if ((float) $promotion->discount_value <= 0) {
            return 0.0;
        }

        $amount = $bundleGross * ((float) $promotion->discount_value / 100);

        return round(min($amount, $bundleGross), 2);
    }

    /**
     * @param  Collection<int, Promotion>  $promotions
     */
    private function selectPromotionForLine(Collection $promotions, ?Product $product, float $qty): ?Promotion
    {
        if ($product === null) {
            return null;
        }

        $matches = $promotions
            ->filter(fn (Promotion $promotion) => $this->matchesProduct($promotion, $product) && $this->meetsMinQty($promotion, $qty))
            ->sortByDesc(fn (Promotion $promotion) => $promotion->priority)
            ->values();

        if ($matches->isEmpty()) {
            return null;
        }

        $exclusive = $matches->first(fn (Promotion $promotion) => $promotion->exclusive);

        return $exclusive ?? $matches->first();
    }

    /**
     * @param  Collection<int, Promotion>  $bogoPromotions
     */
    private function selectBogoPromotionForLine(Collection $bogoPromotions, ?Product $product, float $paidQty): ?Promotion
    {
        if ($product === null) {
            return null;
        }

        $matches = $bogoPromotions
            ->filter(function (Promotion $promotion) use ($product, $paidQty) {
                if (! $this->matchesProduct($promotion, $product)) {
                    return false;
                }

                $buyQty = (int) ($promotion->buy_qty ?? 0);
                $getQty = (int) ($promotion->get_qty ?? 0);

                return $this->calculateBogoFreeUnits((int) $paidQty, $buyQty, $getQty) > 0
                    && $this->meetsMinQty($promotion, $paidQty);
            })
            ->sortByDesc(fn (Promotion $promotion) => [$promotion->priority, (int) ($promotion->buy_qty ?? 0)])
            ->values();

        if ($matches->isEmpty()) {
            return null;
        }

        $exclusive = $matches->first(fn (Promotion $promotion) => $promotion->exclusive);

        return $exclusive ?? $matches->first();
    }

    private function meetsMinQty(Promotion $promotion, float $qty): bool
    {
        if ($promotion->min_qty === null) {
            return true;
        }

        return $qty >= (float) $promotion->min_qty;
    }

    /** @param  array<string, mixed>  $item */
    private function linePhysicalQuantity(array $item): float
    {
        return (float) $item['quantity'] + (float) ($item['free_quantity'] ?? 0);
    }

    private function calculateBogoFreeUnits(int $paidQty, int $buyQty, int $getQty): int
    {
        if ($buyQty <= 0 || $getQty <= 0 || $paidQty < $buyQty) {
            return 0;
        }

        return (int) (floor($paidQty / $buyQty) * $getQty);
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @return array<int, array<string, mixed>>
     */
    private function prepareItemsForPromotion(array $items): array
    {
        return array_map(fn (array $item): array => [
            ...$item,
            'free_quantity' => 0,
        ], $items);
    }

    private function computePromoUnitPrice(Promotion $promotion, float $basePrice, float $qty): float
    {
        if ($basePrice <= 0) {
            return 0.0;
        }

        $lineGross = $basePrice * $qty;

        $discountAmount = match ($promotion->type) {
            PromotionType::Percent => $this->percentDiscount($lineGross, (float) $promotion->discount_value),
            PromotionType::Flat => min((float) $promotion->discount_value, $lineGross),
            PromotionType::FixedPrice => max(0, $lineGross - ((float) $promotion->fixed_price * $qty)),
            default => 0.0,
        };

        $finalLine = max(0, $lineGross - $discountAmount);

        return round($finalLine / max($qty, 1), 2);
    }

    private function percentDiscount(float $base, float $percent): float
    {
        $amount = $base * $percent / 100;

        return round(min($amount, $base), 2);
    }

    /**
     * @return array{0: float, 1: float}
     */
    private function resolveCatalogPrices(?Product $product, ?int $variationId): array
    {
        if ($product === null) {
            return [0.0, 0.0];
        }

        if ($variationId !== null) {
            $variation = $product->relationLoaded('variations')
                ? $product->variations->firstWhere('id', $variationId)
                : ProductVariation::query()->find($variationId);
            $price = (float) ($variation?->price ?? $product->sale_price);

            return [$price, $price];
        }

        $basePrice = (float) $product->sale_price;
        $catalogPrice = (float) $product->discount_price > 0
            ? (float) $product->discount_price
            : $basePrice;

        return [$basePrice, $catalogPrice];
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @param  Collection<int, Promotion>  $promotions
     * @return array<string, bool>
     */
    private function resolveStackingFlags(array $items, Collection $promotions): array
    {
        $appliedPromotionIds = collect($items)
            ->pluck('promotion_id')
            ->filter()
            ->unique()
            ->map(fn ($id) => (int) $id);

        $applied = $promotions->whereIn('id', $appliedPromotionIds);

        if ($applied->isEmpty()) {
            return [
                'manual_line_discount' => true,
                'invoice_discount' => true,
                'special_discount' => true,
            ];
        }

        return [
            'manual_line_discount' => $applied->every(fn (Promotion $promotion) => $promotion->stack_with_manual_line_discount),
            'invoice_discount' => $applied->every(fn (Promotion $promotion) => $promotion->stack_with_invoice_discount),
            'special_discount' => $applied->every(fn (Promotion $promotion) => $promotion->stack_with_special_discount),
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @return Collection<int, Product>
     */
    private function loadProductsForItems(array $items, ?int $branchId): Collection
    {
        $productIds = collect($items)->pluck('product_id')->unique()->filter()->map(fn ($id) => (int) $id);

        if ($productIds->isEmpty()) {
            return collect();
        }

        return Product::query()
            ->with(['variations' => fn ($query) => $query->when($branchId, fn ($q) => $q->where('branch_id', $branchId))])
            ->when($branchId, fn ($query) => $query->where('branch_id', $branchId))
            ->whereIn('id', $productIds)
            ->get()
            ->keyBy('id');
    }

    /** @return Collection<int, Promotion> */
    private function loadPromotions(?int $branchId, string|\DateTimeInterface|null $saleDate = null, bool $scheduleSaleDate = true): Collection
    {
        $cacheKey = ($branchId ?? 'all').'|'.($scheduleSaleDate ? (string) $saleDate : 'all-scheduled');

        if (isset($this->cachedPromotions[$cacheKey])) {
            return $this->cachedPromotions[$cacheKey];
        }

        $query = Promotion::query()
            ->with('targets')
            ->active()
            ->when($branchId, fn ($query) => $query->accessibleAtBranch($branchId))
            ->orderByDesc('priority');

        if ($scheduleSaleDate) {
            $query->activeOnSaleDate($saleDate);
        }

        $this->cachedPromotions[$cacheKey] = $query->get();

        return $this->cachedPromotions[$cacheKey];
    }

    /** @return array<string, mixed> */
    private function serializePromotion(Promotion $promotion): array
    {
        return [
            'id' => $promotion->id,
            'name' => $promotion->name,
            'description' => $promotion->description,
            'scope' => $promotion->scope->value,
            'type' => $promotion->type->value,
            'discount_value' => (float) $promotion->discount_value,
            'fixed_price' => $promotion->fixed_price !== null ? (float) $promotion->fixed_price : null,
            'buy_qty' => $promotion->buy_qty,
            'get_qty' => $promotion->get_qty,
            'get_discount_percent' => $promotion->get_discount_percent !== null ? (float) $promotion->get_discount_percent : null,
            'bundle_product_ids' => $promotion->bundle_product_ids ?? [],
            'min_qty' => $promotion->min_qty !== null ? (float) $promotion->min_qty : null,
            'starts_at' => $promotion->starts_at?->toIso8601String(),
            'ends_at' => $promotion->ends_at?->toIso8601String(),
            'priority' => $promotion->priority,
            'stack_with_product_discount' => $promotion->stack_with_product_discount,
            'stack_with_manual_line_discount' => $promotion->stack_with_manual_line_discount,
            'stack_with_invoice_discount' => $promotion->stack_with_invoice_discount,
            'stack_with_special_discount' => $promotion->stack_with_special_discount,
            'exclusive' => $promotion->exclusive,
            'target_ids' => $promotion->targets
                ->where('target_type', $promotion->scope->value)
                ->pluck('target_id')
                ->map(fn ($id) => (int) $id)
                ->values()
                ->all(),
        ];
    }
}
