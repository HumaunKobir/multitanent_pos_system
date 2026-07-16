<?php

namespace App\Services;

use App\Models\ProductExchange;
use App\Models\ProductExchangeProduct;
use App\Models\SaleReturn;
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
        return round($this->netAmountWithExchange($sell) - $this->saleReturnNetTotal($sell), 2);
    }

    /**
     * Sale net amount adjusted for its Product Exchange price difference, without
     * netting out linked Sale Returns (those are accounted for separately).
     */
    public function netAmountWithExchange(Sell $sell): float
    {
        $base = (float) $sell->net_amount;
        $exchange = $this->resolveExchange($sell);

        return $exchange ? $base + (float) $exchange->price_difference : $base;
    }

    public function effectivePaidAmount(Sell $sell): float
    {
        $base = (float) $sell->paid_amount;
        $exchange = $this->resolveExchange($sell);

        // A positive settlement means the customer paid extra; a refund (negative
        // settlement) means cash flowed back to the customer, reducing what they paid.
        $signedExchangePaid = 0.0;

        if ($exchange) {
            $signedExchangePaid = (float) $exchange->price_difference < 0
                ? -(float) $exchange->paid_amount
                : (float) $exchange->paid_amount;
        }

        // Cash refunded on a Sale Return also reduces what the customer effectively paid.
        return round($base + $signedExchangePaid - $this->saleReturnPaidTotal($sell), 2);
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
        [$originalReturnedByLine, $replacementReturnedByExchangeLine] = $this->saleReturnQuantities($sell);

        if (! $exchange) {
            $effective = [];

            foreach ($sell->products as $line) {
                $effective[] = $this->effectiveOriginalLine($line, (float) ($originalReturnedByLine[$line->id] ?? 0));
            }

            return array_values(array_filter($effective));
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
            $returnedIndependently = (float) ($originalReturnedByLine[$line->id] ?? 0);

            if (! $exchangeLine) {
                $originalLine = $this->effectiveOriginalLine($line, $returnedIndependently);

                if ($originalLine !== null) {
                    $effective[] = $originalLine;
                }

                continue;
            }

            $soldQty = (float) $line->quantity;
            $exchangedQty = (float) $exchangeLine->old_quantity;
            $returnedQty = (float) $exchangeLine->return_quantity;
            // Returned quantity is refunded and removed from the sale entirely, so it
            // no longer appears as an effective product line. A separate Sale Return
            // against the same original line further reduces what remains.
            $remainingQty = round($soldQty - $exchangedQty - $returnedQty - $returnedIndependently, 2);

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

            // A Sale Return of the replacement itself further reduces what remains issued.
            $replacementReturned = (float) ($replacementReturnedByExchangeLine[$exchangeLine->id] ?? 0);
            $replacementRemaining = round((float) $exchangeLine->new_quantity - $replacementReturned, 2);

            if ($replacementRemaining <= 0.001) {
                continue;
            }

            $replacementProportion = (float) $exchangeLine->new_quantity > 0
                ? $replacementRemaining / (float) $exchangeLine->new_quantity
                : 0;

            $effective[] = [
                'id' => $line->id,
                'product_id' => $exchangeLine->new_product_id,
                'variation_id' => $exchangeLine->new_variation_id,
                'product' => $exchangeLine->newProduct,
                'variation' => $exchangeLine->newVariation,
                'promotion' => $exchangeLine->newPromotion,
                'quantity' => $replacementRemaining,
                'free_quantity' => round((float) $exchangeLine->new_free_quantity * $replacementProportion, 2),
                'unit_price' => (float) $exchangeLine->new_unit_price,
                'original_unit_price' => (float) ($exchangeLine->new_original_unit_price ?? $exchangeLine->new_unit_price),
                'discount' => round((float) $exchangeLine->new_line_discount * $replacementProportion, 2),
                'promotion_id' => $exchangeLine->new_promotion_id,
                'promotion_discount' => round((float) $exchangeLine->new_promotion_discount * $replacementProportion, 2),
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
    private function effectiveOriginalLine(SellProduct $line, float $returnedIndependently): ?array
    {
        if ($returnedIndependently <= 0) {
            return $this->formatProductLine($line);
        }

        $soldQty = (float) $line->quantity;
        $remaining = round($soldQty - $returnedIndependently, 2);

        if ($remaining <= 0.001) {
            return null;
        }

        $proportion = $soldQty > 0 ? $remaining / $soldQty : 0;

        return $this->formatProductLine($line, [
            'quantity' => $remaining,
            'free_quantity' => round((float) $line->free_quantity * $proportion, 2),
            'discount' => round((float) $line->discount * $proportion, 2),
            'promotion_discount' => round((float) $line->promotion_discount * $proportion, 2),
        ]);
    }

    /**
     * Quantity already returned via Sale Return, split into original-line returns
     * (keyed by sell_product_id) and exchange-replacement returns (keyed by
     * product_exchange_product_id).
     *
     * @return array{0: array<int, float>, 1: array<int, float>}
     */
    private function saleReturnQuantities(Sell $sell): array
    {
        $original = [];
        $replacement = [];

        foreach ($this->resolveSaleReturns($sell) as $return) {
            foreach ($return->products as $line) {
                if ($line->product_exchange_product_id !== null) {
                    $replacement[$line->product_exchange_product_id] = ($replacement[$line->product_exchange_product_id] ?? 0) + (float) $line->quantity;

                    continue;
                }

                $original[$line->sell_product_id] = ($original[$line->sell_product_id] ?? 0) + (float) $line->quantity;
            }
        }

        return [$original, $replacement];
    }

    private function saleReturnNetTotal(Sell $sell): float
    {
        return round($this->resolveSaleReturns($sell)->sum(fn (SaleReturn $r) => (float) $r->net_amount), 2);
    }

    private function saleReturnPaidTotal(Sell $sell): float
    {
        return round($this->resolveSaleReturns($sell)->sum(fn (SaleReturn $r) => (float) $r->paid_amount), 2);
    }

    /**
     * @return Collection<int, SaleReturn>
     */
    private function resolveSaleReturns(Sell $sell): Collection
    {
        if ($sell->relationLoaded('saleReturns')) {
            $sell->saleReturns->each(fn (SaleReturn $r) => $r->loadMissing('products'));

            return $sell->saleReturns;
        }

        return $sell->saleReturns()->with('products')->get();
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
        $latestReturn = $this->resolveLatestSaleReturn($sell);

        return [
            'has_exchange' => $exchange !== null,
            'exchange_invoice_number' => $exchange?->invoice_number,
            'has_return' => $latestReturn !== null,
            'return_invoice_number' => $latestReturn?->invoice_number,
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
     * Signed outstanding balance for the sale after any exchange overlay.
     * Positive means the customer still owes the shop; negative means the shop
     * owes the customer a refund (e.g. a customer-account exchange refund).
     */
    public function effectiveOutstanding(Sell $sell): float
    {
        return round($this->effectiveNetAmount($sell) - $this->effectivePaidAmount($sell), 2);
    }

    /**
     * @param  Collection<int, Sell>  $sales
     */
    public function sumEffectiveDue(Collection $sales): float
    {
        return round($sales->sum(fn (Sell $sell) => max(0, $this->effectiveOutstanding($sell))), 2);
    }

    /**
     * @param  Collection<int, Sell>  $sales
     */
    public function sumEffectiveRefundDue(Collection $sales): float
    {
        return round($sales->sum(fn (Sell $sell) => max(0, -$this->effectiveOutstanding($sell))), 2);
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

    private function resolveLatestSaleReturn(Sell $sell): ?SaleReturn
    {
        if ($sell->relationLoaded('saleReturns')) {
            return $sell->saleReturns->sortByDesc('id')->first();
        }

        return $sell->saleReturns()->latest('id')->first();
    }
}
