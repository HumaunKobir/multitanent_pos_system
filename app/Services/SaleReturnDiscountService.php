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
        private CoinService $coinService,
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

    public function parentNetBeforeCoin(Sell $parent): float
    {
        return round(
            (float) $parent->gross_amount
            + (float) $parent->vat
            - (float) $parent->discount
            - (float) $parent->special_discount_amount
            - (float) $parent->round_off_amount
            - $parent->lineDiscountTotal(),
            2
        );
    }

    public function coinDiscountClawback(Sell $parent, float $returnNetBeforeCoin): float
    {
        $parentCoinDiscount = (float) $parent->coin_discount_amount;

        if ($parentCoinDiscount <= 0) {
            return 0.0;
        }

        $parentNetBeforeCoin = $this->parentNetBeforeCoin($parent);

        if ($parentNetBeforeCoin <= 0) {
            return 0.0;
        }

        $returnNetBeforeCoin = round(max(0, $returnNetBeforeCoin), 2);
        $remainingNetBeforeCoin = round(max(0, $parentNetBeforeCoin - $returnNetBeforeCoin), 2);
        $remainingCoinDiscount = $this->coinDiscountForPortion($parent, $remainingNetBeforeCoin, $parentNetBeforeCoin);

        return round(max(0, $parentCoinDiscount - $remainingCoinDiscount), 2);
    }

    /**
     * @return array{
     *     discount_amount: float,
     *     return_line_discount: float,
     *     return_promotion_discount: float,
     *     return_invoice_discount: float,
     *     return_special_discount: float,
     *     return_round_off: float,
     *     return_coin_discount: float,
     *     net_return_amount: float
     * }
     */
    public function calculate(
        Sell $parent,
        float $returnGross,
        float $returnLineDiscount,
        float $returnPromotionDiscount,
        ?float $manualInvoiceDiscount = null,
        ?float $manualSpecialDiscount = null,
        ?float $manualRoundOff = null,
        ?float $manualCoinDiscount = null,
    ): array {
        $parentGross = (float) $parent->gross_amount;
        $parentLineDiscount = $parent->lineDiscountTotal();
        $parentNetForProportion = $parentGross - $parentLineDiscount;

        $returnAfterPromo = $returnGross - $returnLineDiscount - $returnPromotionDiscount;
        $proportion = $parentNetForProportion > 0 ? $returnAfterPromo / $parentNetForProportion : 0;

        $returnInvoiceDiscount = $manualInvoiceDiscount !== null
            ? round(min(max(0, $manualInvoiceDiscount), (float) $parent->discount), 2)
            : round($proportion * (float) $parent->discount, 2);

        $returnSpecialDiscount = $manualSpecialDiscount !== null
            ? round(min(max(0, $manualSpecialDiscount), (float) $parent->special_discount_amount), 2)
            : round($proportion * (float) $parent->special_discount_amount, 2);

        $returnRoundOff = $manualRoundOff !== null
            ? round(min(max(0, $manualRoundOff), (float) $parent->round_off_amount), 2)
            : round($proportion * (float) $parent->round_off_amount, 2);

        $returnInvoiceLevelDiscount = $returnInvoiceDiscount + $returnSpecialDiscount + $returnRoundOff;
        $returnNetBeforeCoin = round(max(0, $returnAfterPromo - $returnInvoiceLevelDiscount), 2);

        $returnCoinDiscount = $manualCoinDiscount !== null
            ? round(min(max(0, $manualCoinDiscount), (float) $parent->coin_discount_amount), 2)
            : $this->coinDiscountClawback($parent, $returnNetBeforeCoin);

        $discountAmount = round(
            $returnLineDiscount + $returnPromotionDiscount + $returnInvoiceLevelDiscount + $returnCoinDiscount,
            2,
        );
        $netReturnAmount = round(max(0, $returnGross - $discountAmount), 2);

        return [
            'discount_amount' => $discountAmount,
            'return_line_discount' => $returnLineDiscount,
            'return_promotion_discount' => $returnPromotionDiscount,
            'return_invoice_discount' => $returnInvoiceDiscount,
            'return_special_discount' => $returnSpecialDiscount,
            'return_round_off' => $returnRoundOff,
            'return_coin_discount' => $returnCoinDiscount,
            'net_return_amount' => $netReturnAmount,
        ];
    }

    private function coinDiscountForPortion(Sell $parent, float $netBeforeCoin, float $parentNetBeforeCoin): float
    {
        if ($netBeforeCoin <= 0) {
            return 0.0;
        }

        $parent->loadMissing('customer');
        $settings = $this->coinService->settingsForBranch($parent->branch_id);
        $customer = $parent->customer;

        if ($settings === null || $customer === null || $customer->is_default) {
            return round((float) $parent->coin_discount_amount * ($netBeforeCoin / $parentNetBeforeCoin), 2);
        }

        $proportion = $netBeforeCoin / $parentNetBeforeCoin;
        $coinsForPortion = round((float) $parent->coins_redeemed * $proportion, 2);
        $maxRedeemable = $this->coinService->maxRedeemableCoins($customer, $settings, $netBeforeCoin);
        $allowedCoins = min($coinsForPortion, $maxRedeemable);

        return $this->coinService->redeemDiscount($allowedCoins, $settings, $netBeforeCoin);
    }
}
