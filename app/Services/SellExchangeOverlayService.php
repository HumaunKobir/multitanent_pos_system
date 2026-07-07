<?php

namespace App\Services;

use App\Models\ProductExchange;
use App\Models\ProductExchangeProduct;
use App\Models\Sell;
use App\Models\SellProduct;
use Illuminate\Support\Collection;

class SellExchangeOverlayService
{
    public function hasExchange(Sell $sell): bool
    {
        if ($sell->relationLoaded('productExchange')) {
            return $sell->productExchange !== null;
        }

        return $sell->productExchange()->exists();
    }

    public function effectiveNetAmount(Sell $sell): float
    {
        $base = (float) $sell->net_amount;
        $exchange = $this->resolveExchange($sell);

        if (! $exchange) {
            return round($base, 2);
        }

        return round($base + (float) $exchange->price_difference, 2);
    }

    public function effectivePaidAmount(Sell $sell): float
    {
        $base = (float) $sell->paid_amount;
        $exchange = $this->resolveExchange($sell);

        if (! $exchange) {
            return round($base, 2);
        }

        // A positive settlement means the customer paid extra; a refund (negative
        // settlement) means cash flowed back to the customer, reducing what they paid.
        $signedPaid = (float) $exchange->price_difference < 0
            ? -(float) $exchange->paid_amount
            : (float) $exchange->paid_amount;

        return round($base + $signedPaid, 2);
    }

    public function effectiveDueAmount(Sell $sell): float
    {
        return round(max(0, $this->effectiveNetAmount($sell) - $this->effectivePaidAmount($sell)), 2);
    }

    public function effectiveGrossAmount(Sell $sell): float
    {
        return round(collect($this->effectiveProducts($sell))->sum(
            fn (array $line) => (float) $line['quantity'] * (float) $line['unit_price']
        ), 2);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function effectiveProducts(Sell $sell): array
    {
        $sell->loadMissing(['products.product', 'products.variation', 'products.promotion']);

        $exchange = $this->resolveExchange($sell);

        if (! $exchange) {
            return $sell->products
                ->map(fn (SellProduct $line) => $this->formatProductLine($line))
                ->values()
                ->all();
        }

        $exchange->loadMissing([
            'products.newProduct',
            'products.newVariation',
            'products.newPromotion',
        ]);

        $exchangeLines = $exchange->products->keyBy('sell_product_id');
        $effective = [];

        foreach ($sell->products as $line) {
            /** @var ProductExchangeProduct|null $exchangeLine */
            $exchangeLine = $exchangeLines->get($line->id);

            if (! $exchangeLine) {
                $effective[] = $this->formatProductLine($line);

                continue;
            }

            $soldQty = (float) $line->quantity;
            $exchangedQty = (float) $exchangeLine->old_quantity;
            $returnedQty = (float) $exchangeLine->return_quantity;
            // Returned quantity is refunded and removed from the sale entirely, so it
            // no longer appears as an effective product line.
            $remainingQty = round($soldQty - $exchangedQty - $returnedQty, 2);

            if ($remainingQty > 0.001) {
                $proportion = $soldQty > 0 ? $remainingQty / $soldQty : 0;

                $effective[] = $this->formatProductLine($line, [
                    'quantity' => $remainingQty,
                    'free_quantity' => round((float) $line->free_quantity * $proportion, 2),
                    'discount' => round((float) $line->discount * $proportion, 2),
                    'promotion_discount' => round((float) $line->promotion_discount * $proportion, 2),
                ]);
            }

            // A pure return (no swap) has no replacement product to show.
            if ((float) $exchangeLine->new_quantity <= 0) {
                continue;
            }

            $effective[] = [
                'id' => $line->id,
                'product_id' => $exchangeLine->new_product_id,
                'variation_id' => $exchangeLine->new_variation_id,
                'product' => $exchangeLine->newProduct,
                'variation' => $exchangeLine->newVariation,
                'promotion' => $exchangeLine->newPromotion,
                'quantity' => (float) $exchangeLine->new_quantity,
                'free_quantity' => (float) $exchangeLine->new_free_quantity,
                'unit_price' => (float) $exchangeLine->new_unit_price,
                'original_unit_price' => (float) ($exchangeLine->new_original_unit_price ?? $exchangeLine->new_unit_price),
                'discount' => (float) $exchangeLine->new_line_discount,
                'promotion_id' => $exchangeLine->new_promotion_id,
                'promotion_discount' => (float) $exchangeLine->new_promotion_discount,
                'promotion_meta' => $exchangeLine->new_promotion_meta,
                'is_exchange_replacement' => true,
                'replaced_product_id' => $exchangeLine->old_product_id,
                'replaced_quantity' => (float) $exchangeLine->old_quantity,
            ];
        }

        return $effective;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function originalSaleSnapshot(Sell $sell): ?array
    {
        if (! $this->hasExchange($sell)) {
            return null;
        }

        $sell->loadMissing(['products.product', 'products.variation', 'products.promotion']);

        return [
            'gross_amount' => (float) $sell->gross_amount,
            'vat' => (float) $sell->vat,
            'discount' => (float) $sell->discount,
            'special_discount_amount' => (float) $sell->special_discount_amount,
            'promotion_discount_total' => (float) $sell->promotion_discount_total,
            'coin_discount_amount' => (float) $sell->coin_discount_amount,
            'round_off_amount' => (float) $sell->round_off_amount,
            'net_amount' => (float) $sell->net_amount,
            'paid_amount' => (float) $sell->paid_amount,
            'products' => $sell->products
                ->map(fn (SellProduct $line) => $this->formatProductLine($line))
                ->values()
                ->all(),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function exchangeSummary(Sell $sell): ?array
    {
        $exchange = $this->resolveExchange($sell);

        if (! $exchange) {
            return null;
        }

        $exchange->loadMissing([
            'products.oldProduct',
            'products.newProduct',
            'products.oldVariation',
            'products.newVariation',
        ]);

        return [
            'id' => $exchange->id,
            'invoice_number' => $exchange->invoice_number,
            'date' => optional($exchange->date)->format('Y-m-d'),
            'price_difference' => (float) $exchange->price_difference,
            'paid_amount' => (float) $exchange->paid_amount,
            'net_amount' => (float) $exchange->net_amount,
            'return_refund_amount' => (float) $exchange->return_refund_amount,
            'lines' => $exchange->products
                ->map(fn (ProductExchangeProduct $line) => [
                    'sell_product_id' => $line->sell_product_id,
                    'old_product' => $line->oldProduct?->only(['id', 'name', 'code']),
                    'old_variation_label' => $line->oldVariation?->variation_data['label'] ?? null,
                    'old_quantity' => (float) $line->old_quantity,
                    'old_unit_price' => (float) $line->old_unit_price,
                    'return_quantity' => (float) $line->return_quantity,
                    'return_refund_amount' => (float) $line->return_refund_amount,
                    'new_product' => $line->newProduct?->only(['id', 'name', 'code']),
                    'new_variation_label' => $line->newVariation?->variation_data['label'] ?? null,
                    'new_quantity' => (float) $line->new_quantity,
                    'new_unit_price' => (float) $line->new_unit_price,
                ])
                ->values()
                ->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function indexRowOverlay(Sell $sell): array
    {
        $exchange = $this->resolveExchange($sell);

        return [
            'has_exchange' => $exchange !== null,
            'exchange_invoice_number' => $exchange?->invoice_number,
        ];
    }

    /**
     * @param  Collection<int, Sell>  $sales
     */
    public function sumEffectiveNet(Collection $sales): float
    {
        return round($sales->sum(fn (Sell $sell) => $this->effectiveNetAmount($sell)), 2);
    }

    /**
     * @param  Collection<int, Sell>  $sales
     */
    public function sumEffectivePaid(Collection $sales): float
    {
        return round($sales->sum(fn (Sell $sell) => $this->effectivePaidAmount($sell)), 2);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function formatProductLine(SellProduct $line, array $overrides = []): array
    {
        $quantity = (float) ($overrides['quantity'] ?? $line->quantity);
        $unitPrice = (float) ($overrides['unit_price'] ?? $line->unit_price);

        return [
            'id' => $line->id,
            'product_id' => $line->product_id,
            'variation_id' => $line->variation_id,
            'product' => $line->product,
            'variation' => $line->variation,
            'promotion' => $line->promotion,
            'quantity' => $quantity,
            'free_quantity' => (float) ($overrides['free_quantity'] ?? $line->free_quantity),
            'unit_price' => $unitPrice,
            'original_unit_price' => (float) ($overrides['original_unit_price'] ?? $line->original_unit_price ?? $line->unit_price),
            'discount' => (float) ($overrides['discount'] ?? $line->discount),
            'promotion_id' => $line->promotion_id,
            'promotion_discount' => (float) ($overrides['promotion_discount'] ?? $line->promotion_discount),
            'promotion_meta' => $line->promotion_meta,
            'is_exchange_replacement' => false,
        ];
    }

    private function resolveExchange(Sell $sell): ?ProductExchange
    {
        if ($sell->relationLoaded('productExchange')) {
            return $sell->productExchange;
        }

        return $sell->productExchange()->first();
    }
}
