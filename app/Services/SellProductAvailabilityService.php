<?php

namespace App\Services;

use App\Models\ProductExchangeProduct;
use App\Models\SaleReturn;
use App\Models\SaleReturnProduct;
use App\Models\SellProduct;

/**
 * Shared quantity-pool math for the sale return / product exchange coexistence rules:
 * an original sale line's remaining quantity is reduced by both the returns made
 * directly against it and any exchange that swapped/returned part of it, and a
 * replacement product issued by an exchange has its own independent returnable pool.
 */
class SellProductAvailabilityService
{
    /**
     * Quantity already returned against each original sell line (excludes lines that
     * return an exchange replacement — those consume the replacement's own pool instead).
     *
     * @return array<int, float>
     */
    public function returnedQuantitiesByLine(int $sellId, ?int $excludeSaleReturnId = null): array
    {
        $quantities = [];

        SaleReturn::query()
            ->where('sell_id', $sellId)
            ->when($excludeSaleReturnId, fn ($q) => $q->whereKeyNot($excludeSaleReturnId))
            ->with('products')
            ->get()
            ->each(function (SaleReturn $return) use (&$quantities) {
                foreach ($return->products as $line) {
                    if ($line->product_exchange_product_id !== null) {
                        continue;
                    }

                    $quantities[$line->sell_product_id] = ($quantities[$line->sell_product_id] ?? 0) + (float) $line->quantity;
                }
            });

        return $quantities;
    }

    /**
     * Quantity consumed by the sale's exchange (swap-out + refund-without-replacement),
     * keyed by the original sell line it was taken from. A sale has at most one exchange
     * document, so excludeExchangeId only matters when recomputing that same document.
     *
     * @return array<int, float>
     */
    public function exchangedQuantitiesByLine(int $sellId, ?int $excludeExchangeId = null): array
    {
        $quantities = [];

        ProductExchangeProduct::query()
            ->whereHas('productExchange', function ($q) use ($sellId, $excludeExchangeId) {
                $q->where('sell_id', $sellId);

                if ($excludeExchangeId !== null) {
                    $q->whereKeyNot($excludeExchangeId);
                }
            })
            ->get()
            ->each(function (ProductExchangeProduct $line) use (&$quantities) {
                $consumed = (float) $line->old_quantity + (float) $line->return_quantity;
                $quantities[$line->sell_product_id] = ($quantities[$line->sell_product_id] ?? 0) + $consumed;
            });

        return $quantities;
    }

    /**
     * Quantity actually swapped for a replacement product (old_quantity only — excludes the
     * exchange's own refund-without-replacement portion). Display-only breakdown: pair with
     * exchangedQuantitiesByLine() to derive the refund-without-replacement share
     * (combined − swap) so a "Returned" column can include it instead of "Exchanged".
     *
     * @return array<int, float>
     */
    public function exchangeSwapQuantitiesByLine(int $sellId, ?int $excludeExchangeId = null): array
    {
        $quantities = [];

        ProductExchangeProduct::query()
            ->whereHas('productExchange', function ($q) use ($sellId, $excludeExchangeId) {
                $q->where('sell_id', $sellId);

                if ($excludeExchangeId !== null) {
                    $q->whereKeyNot($excludeExchangeId);
                }
            })
            ->get()
            ->each(function (ProductExchangeProduct $line) use (&$quantities) {
                $swap = (float) $line->old_quantity;

                if ($swap > 0) {
                    $quantities[$line->sell_product_id] = ($quantities[$line->sell_product_id] ?? 0) + $swap;
                }
            });

        return $quantities;
    }

    public function availableQuantity(
        SellProduct $line,
        int $sellId,
        ?int $excludeSaleReturnId = null,
        ?int $excludeExchangeId = null,
    ): float {
        $returned = $this->returnedQuantitiesByLine($sellId, $excludeSaleReturnId)[$line->id] ?? 0.0;
        $exchanged = $this->exchangedQuantitiesByLine($sellId, $excludeExchangeId)[$line->id] ?? 0.0;

        return max(0.0, (float) $line->quantity - $returned - $exchanged);
    }

    /**
     * Quantity of a specific exchange replacement line already returned via Sale Return.
     */
    public function returnedReplacementQuantity(int $productExchangeProductId, ?int $excludeSaleReturnId = null): float
    {
        return (float) SaleReturnProduct::query()
            ->where('product_exchange_product_id', $productExchangeProductId)
            ->when($excludeSaleReturnId, fn ($q) => $q->where('sale_return_id', '!=', $excludeSaleReturnId))
            ->sum('quantity');
    }

    public function availableReplacementQuantity(ProductExchangeProduct $line, ?int $excludeSaleReturnId = null): float
    {
        $returned = $this->returnedReplacementQuantity($line->id, $excludeSaleReturnId);

        return max(0.0, (float) $line->new_quantity - $returned);
    }
}
