<?php

namespace App\Services;

use App\Models\Batch;
use App\Models\Damage;
use App\Models\ProductVariation;
use App\Models\SaleReturn;
use App\Models\Sell;
use App\Models\StockAdjustment;
use App\Models\StockDistribution;
use App\Models\StockDistributionProduct;

class InventoryCostService
{
    /**
     * @param  array<int|string, float>  $batchMap
     */
    public function costFromBatchMap(array $batchMap): float
    {
        if ($batchMap === []) {
            return 0.0;
        }

        $batches = Batch::query()
            ->whereIn('id', array_keys($batchMap))
            ->get(['id', 'purchase_price'])
            ->keyBy('id');

        $total = 0.0;

        foreach ($batchMap as $batchId => $quantity) {
            $batch = $batches->get((int) $batchId);

            if ($batch === null) {
                continue;
            }

            $total += (float) $batch->purchase_price * (float) $quantity;
        }

        return round($total, 2);
    }

    public function costFromVariation(?int $variationId, float $quantity): float
    {
        if ($variationId === null || $quantity <= 0) {
            return 0.0;
        }

        $variation = ProductVariation::query()->find($variationId);

        if ($variation === null) {
            return 0.0;
        }

        return round((float) $variation->purchase_price * $quantity, 2);
    }

    /**
     * @param  array<int|string, float>  $batchMap
     */
    public function costForLine(?int $variationId, float $quantity, array $batchMap): float
    {
        if ($batchMap !== []) {
            return $this->costFromBatchMap($batchMap);
        }

        return $this->costFromVariation($variationId, $quantity);
    }

    public function costForDamage(Damage $damage): float
    {
        $damage->loadMissing('products');

        $total = 0.0;

        foreach ($damage->products as $line) {
            $total += $this->costForLine(
                $line->variation_id ? (int) $line->variation_id : null,
                (float) $line->quantity,
                $line->batches ?? [],
            );
        }

        return round($total, 2);
    }

    public function costForStockAdjustment(StockAdjustment $adjustment): float
    {
        $adjustment->loadMissing(['products.product:id,purchase_price', 'products.variation:id,purchase_price']);

        $total = 0.0;

        foreach ($adjustment->products as $line) {
            $batchMap = is_array($line->batches) ? $line->batches : [];

            if ($batchMap !== []) {
                $total += $this->costFromBatchMap($batchMap);

                continue;
            }

            $unitCost = (float) $line->unit_cost;

            if ($unitCost <= 0) {
                $unitCost = $line->variation_id
                    ? (float) ($line->variation?->purchase_price ?? 0)
                    : (float) ($line->product?->purchase_price ?? 0);
            }

            $total += round($unitCost * (float) $line->quantity, 2);
        }

        return round($total, 2);
    }

    public function costForSaleReturn(SaleReturn $saleReturn): float
    {
        $saleReturn->loadMissing('products');

        $total = 0.0;

        foreach ($saleReturn->products as $line) {
            $total += $this->costForLine(
                $line->variation_id ? (int) $line->variation_id : null,
                (float) $line->quantity,
                $line->batches ?? [],
            );
        }

        return round($total, 2);
    }

    public function costForOnlineOrder(OnlineOrder $order): float
    {
        $order->loadMissing('products');

        $total = 0.0;

        foreach ($order->products as $line) {
            $total += $this->costForLine(
                $line->variation_id ? (int) $line->variation_id : null,
                (float) $line->quantity,
                $line->batches ?? [],
            );
        }

        return round($total, 2);
    }

    public function costForSell(Sell $sell): float
    {
        $sell->loadMissing('products');

        $total = 0.0;

        foreach ($sell->products as $line) {
            $total += $this->costForLine(
                $line->variation_id ? (int) $line->variation_id : null,
                (float) $line->quantity,
                $line->batches ?? [],
            );
        }

        return round($total, 2);
    }

    public function costForStockDistributionLine(StockDistributionProduct $line): float
    {
        return $this->costForLine(
            $line->variation_id ? (int) $line->variation_id : null,
            (float) $line->quantity,
            $line->source_batches ?? [],
        );
    }

    public function costForStockDistribution(StockDistribution $distribution): float
    {
        $distribution->loadMissing('products');

        $total = 0.0;

        foreach ($distribution->products as $line) {
            $total += $this->costForLine(
                $line->variation_id ? (int) $line->variation_id : null,
                (float) $line->quantity,
                $line->source_batches ?? [],
            );
        }

        return round($total, 2);
    }
}
