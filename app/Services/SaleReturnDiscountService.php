<?php

namespace App\Services;

use App\Enums\PromotionType;
use App\Models\Promotion;
use App\Models\Sell;
use App\Models\SellProduct;

class SaleReturnDiscountService
{
    public function __construct(
        private PromotionService $promotionService,
    ) {}

    public function promotionClawback(SellProduct $line, float $returnQty, Sell $parent): float
    {
        $soldQty = (float) $line->quantity;
        $returnQty = min(max(0, $returnQty), $soldQty);

        if ($returnQty <= 0) {
            return 0.0;
        }

        $originalDiscount = (float) $line->promotion_discount;

        if ($originalDiscount <= 0) {
            return 0.0;
        }

        $remainingQty = $soldQty - $returnQty;

        if ($this->remainingQuantityKeepsPromotion($line, $remainingQty, $parent)) {
            return 0.0;
        }

        // For promotions with a min_qty threshold: only claw back when the return qty itself
        // meets or exceeds the minimum. Returning fewer items than the threshold means the
        // returned portion wasn't individually responsible for triggering the promotion discount.
        $line->loadMissing('promotion');

        if ($line->promotion !== null && $line->promotion->min_qty !== null) {
            if ($returnQty < (float) $line->promotion->min_qty) {
                return 0.0;
            }
        }

        return round($originalDiscount, 2);
    }

    private function remainingQuantityKeepsPromotion(SellProduct $line, float $remainingQty, Sell $parent): bool
    {
        if ($remainingQty <= 0) {
            return false;
        }

        $line->loadMissing('promotion');

        if ($line->promotion !== null) {
            return match ($line->promotion->type) {
                PromotionType::BuyXGetY => $remainingQty >= (float) $line->promotion->buy_qty,
                default => $this->promotionMeetsMinQty($line->promotion, $remainingQty),
            };
        }

        return $this->promotionDiscountAtQuantity($line, $remainingQty, $parent) > 0;
    }

    private function promotionMeetsMinQty(Promotion $promotion, float $qty): bool
    {
        if ($promotion->min_qty === null) {
            return true;
        }

        return $qty >= (float) $promotion->min_qty;
    }

    public function promotionDiscountAtQuantity(SellProduct $line, float $paidQty, Sell $parent): float
    {
        if ($paidQty <= 0) {
            return 0.0;
        }

        $item = [
            'product_id' => $line->product_id,
            'variation_id' => $line->variation_id,
            'quantity' => $paidQty,
            'unit_price' => (float) ($line->original_unit_price ?? $line->unit_price),
            'original_unit_price' => (float) ($line->original_unit_price ?? $line->unit_price),
            'discount' => 0,
        ];

        $saleDate = optional($parent->date)->format('Y-m-d');
        $result = $this->promotionService->applyToCart([$item], $parent->branch_id, $saleDate);

        return (float) ($result['items'][0]['promotion_discount'] ?? 0);
    }

    /**
     * @return array{
     *     discount_amount: float,
     *     return_line_discount: float,
     *     return_promotion_discount: float,
     *     return_invoice_discount: float,
     *     return_round_off: float,
     *     vat_percent: float,
     *     vat_amount: float,
     *     net_return_amount: float
     * }
     */
    public function calculate(
        Sell $parent,
        float $returnGross,
        float $returnLineDiscount,
        float $returnPromotionDiscount,
        ?float $manualInvoiceDiscount = null,
        ?float $manualRoundOff = null,
        ?float $manualVatPercent = null,
    ): array {
        $parentGross = (float) $parent->gross_amount;
        $parentLineDiscount = $parent->lineDiscountTotal();
        $parentNetForProportion = $parentGross - $parentLineDiscount;

        $returnAfterPromo = $returnGross - $returnLineDiscount - $returnPromotionDiscount;
        $proportion = $parentNetForProportion > 0 ? $returnAfterPromo / $parentNetForProportion : 0;

        $returnInvoiceDiscount = $manualInvoiceDiscount !== null
            ? round(min(max(0, $manualInvoiceDiscount), (float) $parent->discount), 2)
            : round($proportion * (float) $parent->discount, 2);

        $returnRoundOff = $manualRoundOff !== null
            ? round(min(max(0, $manualRoundOff), (float) $parent->round_off_amount), 2)
            : round($proportion * (float) $parent->round_off_amount, 2);

        $discountAmount = round(
            $returnLineDiscount + $returnPromotionDiscount + $returnInvoiceDiscount + $returnRoundOff,
            2,
        );
        $returnBase = round(max(0, $returnGross - $discountAmount), 2);

        $vatPercent = max(0, $manualVatPercent ?? 0);
        $returnVat = $vatPercent > 0 ? round($returnBase * ($vatPercent / 100), 2) : 0.0;

        $netReturnAmount = round($returnBase + $returnVat, 2);

        return [
            'discount_amount' => $discountAmount,
            'return_line_discount' => $returnLineDiscount,
            'return_promotion_discount' => $returnPromotionDiscount,
            'return_invoice_discount' => $returnInvoiceDiscount,
            'return_round_off' => $returnRoundOff,
            'vat_percent' => $vatPercent,
            'vat_amount' => $returnVat,
            'net_return_amount' => $netReturnAmount,
        ];
    }
}
