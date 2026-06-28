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
     * Compute a flat or percent discount amount against a base, clamped to the base.
     */
    private function computeDiscountAmount(string $type, float $value, float $base): float
    {
        if ($value <= 0 || $base <= 0) {
            return 0.0;
        }

        $amount = $type === 'percent' ? ($base * $value) / 100 : $value;

        return min($amount, $base);
    }

    /**
     * Invoice discount, round off and VAT mirror how the parent sale builds its totals:
     * VAT is charged on the taxable base (gross − line − promotion discounts), BEFORE the
     * invoice discount and round off are applied — never on the fully discounted base.
     *
     * @return array{
     *     discount_amount: float,
     *     return_line_discount: float,
     *     return_promotion_discount: float,
     *     return_invoice_discount: float,
     *     return_round_off: float,
     *     vat_percent: float,
     *     vat_amount: float,
     *     net_return_amount: float,
     *     max_net_return_amount: float
     * }
     */
    public function calculate(
        Sell $parent,
        float $returnGross,
        float $returnLineDiscount,
        float $returnPromotionDiscount,
        ?string $manualInvoiceDiscountType = null,
        ?float $manualInvoiceDiscountValue = null,
        ?float $manualRoundOff = null,
        ?float $manualVatPercent = null,
    ): array {
        $parentGross = (float) $parent->gross_amount;
        $parentLineDiscount = $parent->lineDiscountTotal();
        $parentNetForProportion = $parentGross - $parentLineDiscount;

        $taxableBase = round(max(0, $returnGross - $returnLineDiscount - $returnPromotionDiscount), 2);
        $proportion = $parentNetForProportion > 0 ? $taxableBase / $parentNetForProportion : 0;

        $returnInvoiceDiscount = $manualInvoiceDiscountValue !== null
            ? round(min(
                $this->computeDiscountAmount(
                    $manualInvoiceDiscountType ?? 'flat',
                    max(0, $manualInvoiceDiscountValue),
                    $taxableBase,
                ),
                $taxableBase,
            ), 2)
            : round($proportion * (float) $parent->discount, 2);

        $roundOffCap = max(0, $taxableBase - $returnInvoiceDiscount);
        $returnRoundOff = $manualRoundOff !== null
            ? round(min(max(0, $manualRoundOff), $roundOffCap), 2)
            : round(min($proportion * (float) $parent->round_off_amount, $roundOffCap), 2);

        $discountAmount = round(
            $returnLineDiscount + $returnPromotionDiscount + $returnInvoiceDiscount + $returnRoundOff,
            2,
        );

        $vatPercent = max(0, $manualVatPercent ?? 0);
        $returnVat = $vatPercent > 0 ? round($taxableBase * ($vatPercent / 100), 2) : 0.0;

        $returnBase = round(max(0, $returnGross - $discountAmount), 2);
        $netReturnAmount = round($returnBase + $returnVat, 2);

        // A return can never be worth more than the matching share of the original sale. The cap
        // mirrors the components a return actually reverses (gross + vat − line − invoice − round
        // off), excluding special/coin discounts the return model does not apply, so legitimate
        // returns are never falsely blocked.
        $parentReturnableNet = $parentGross
            + (float) $parent->vat
            - (float) $parent->discount
            - (float) $parent->round_off_amount
            - $parentLineDiscount;
        $maxNetReturnAmount = round(max(0, $proportion * $parentReturnableNet), 2);

        return [
            'discount_amount' => $discountAmount,
            'return_line_discount' => $returnLineDiscount,
            'return_promotion_discount' => $returnPromotionDiscount,
            'return_invoice_discount' => $returnInvoiceDiscount,
            'return_round_off' => $returnRoundOff,
            'vat_percent' => $vatPercent,
            'vat_amount' => $returnVat,
            'net_return_amount' => $netReturnAmount,
            'max_net_return_amount' => $maxNetReturnAmount,
        ];
    }
}
