<?php

namespace App\Services;

use App\Enums\DiscountType;
use App\Models\Customer;
use App\Models\Sell;
use App\Models\SellProduct;

class ProductExchangeDiscountService
{
    public function __construct(
        private PromotionService $promotionService,
        private SpecialDiscountService $specialDiscountService,
        private SaleReturnDiscountService $saleReturnDiscountService,
        private CoinService $coinService,
    ) {}

    public function resolveLineDiscount(
        SellProduct $line,
        int $newProductId,
        ?int $newVariationId,
        int $exchangeQty,
        float $lineGross,
    ): float {
        if ((int) $line->product_id !== $newProductId) {
            return 0.0;
        }

        if ((int) ($line->variation_id ?? 0) !== (int) ($newVariationId ?? 0)) {
            return 0.0;
        }

        $soldQty = (float) $line->quantity;

        if ($soldQty <= 0 || $exchangeQty <= 0) {
            return 0.0;
        }

        $proportion = min(1, $exchangeQty / $soldQty);
        $discount = round((float) $line->discount * $proportion, 2);

        return round(min($discount, max(0, $lineGross)), 2);
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @return array{
     *     items: array<int, array<string, mixed>>,
     *     promotion_discount_total: float
     * }
     */
    public function resolvePromotions(array $items, Sell $parent, ?int $branchId, string $saleDate): array
    {
        $parent->loadMissing('products');
        $resolvedBySellLine = [];
        $freshItems = [];

        foreach ($items as $item) {
            $sellProductId = (int) $item['sell_product_id'];
            $sellProduct = $parent->products->firstWhere('id', $sellProductId);

            if (! $sellProduct) {
                continue;
            }

            $exchangeQty = (int) $item['quantity'];
            $catalogPrice = (float) $item['unit_price'];
            $newProductId = (int) $item['product_id'];
            $newVariationId = ! empty($item['variation_id']) ? (int) $item['variation_id'] : null;
            $isSameProduct = $newProductId === (int) $sellProduct->product_id
                && (int) ($newVariationId ?? 0) === (int) ($sellProduct->variation_id ?? 0);

            if ($isSameProduct && (float) $sellProduct->promotion_discount > 0) {
                $carriedPromoDiscount = $this->saleReturnDiscountService->carriedPromotionDiscount(
                    $sellProduct,
                    $exchangeQty,
                    $parent,
                );

                if ($carriedPromoDiscount > 0) {
                    $unitPrice = $exchangeQty > 0
                        ? round($catalogPrice - ($carriedPromoDiscount / $exchangeQty), 2)
                        : $catalogPrice;
                    $resolvedBySellLine[$sellProductId] = [
                        'product_id' => $newProductId,
                        'variation_id' => $newVariationId,
                        'quantity' => $exchangeQty,
                        'unit_price' => $unitPrice,
                        'original_unit_price' => $catalogPrice,
                        'promotion_id' => $sellProduct->promotion_id,
                        'promotion_discount' => $carriedPromoDiscount,
                        'free_quantity' => 0,
                        'promotion_meta' => null,
                    ];

                    continue;
                }
            }

            if ($isSameProduct) {
                $resolvedBySellLine[$sellProductId] = $this->defaultPromoLine($item);

                continue;
            }

            $freshItems[] = array_merge($item, [
                'sell_product_id' => $sellProductId,
                'discount' => 0,
            ]);
        }

        if ($freshItems !== []) {
            $cartItems = collect($freshItems)
                ->map(fn (array $item) => [
                    'product_id' => (int) $item['product_id'],
                    'variation_id' => ! empty($item['variation_id']) ? (int) $item['variation_id'] : null,
                    'quantity' => (int) $item['quantity'],
                    'unit_price' => (float) $item['unit_price'],
                    'discount' => 0,
                ])
                ->all();

            $freshResult = $this->promotionService->validateAndResolve($cartItems, $branchId, $saleDate);

            foreach ($freshItems as $index => $item) {
                $sellProductId = (int) $item['sell_product_id'];
                $resolvedBySellLine[$sellProductId] = $freshResult['items'][$index] ?? $this->defaultPromoLine($item);
            }
        }

        $promotionDiscountTotal = round(collect($resolvedBySellLine)->sum(
            fn (array $line) => (float) ($line['promotion_discount'] ?? 0)
        ), 2);

        return [
            'items' => $resolvedBySellLine,
            'promotion_discount_total' => $promotionDiscountTotal,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{
     *     discount: float,
     *     discount_type: DiscountType,
     *     discount_value: float,
     *     vat: float,
     *     round_off_amount: float,
     *     special_discount_id: int|null,
     *     special_discount_amount: float,
     *     promotion_discount_total: float,
     *     coins_redeemed: float,
     *     coin_discount_amount: float,
     *     coins_earned: float,
     *     net_amount: float,
     *     line_discount_total: float
     * }
     */
    public function resolveExchangeTotals(
        array $data,
        Sell $parent,
        float $grossAmount,
        float $lineDiscountTotal,
        float $promotionDiscountTotal,
        ?int $branchId,
    ): array {
        $taxableBase = round(max(0, $grossAmount - $lineDiscountTotal), 2);

        $discountType = isset($data['discount_type'])
            ? ($data['discount_type'] instanceof DiscountType
                ? $data['discount_type']
                : DiscountType::from($data['discount_type']))
            : ($parent->discount_type ?? DiscountType::Flat);
        $discountValue = (float) ($data['discount_value'] ?? $parent->discount_value ?? 0);

        if ($discountType === DiscountType::Percent && $discountValue > 100) {
            $discountValue = 100.0;
        }

        $invoiceDiscount = $this->specialDiscountService->computeAmount(
            $discountType,
            $discountValue,
            $taxableBase,
        );

        $specialDiscountId = $this->normalizedSpecialDiscountId($data, $parent);
        $specialResolved = $specialDiscountId
            ? $this->specialDiscountService->resolveForSale($specialDiscountId, $taxableBase, $branchId)
            : null;
        $specialDiscountAmount = $specialResolved['amount'] ?? 0.0;

        $parentGross = (float) $parent->gross_amount;
        $parentLineDiscount = $parent->lineDiscountTotal();
        $parentTaxableBase = max(0, $parentGross - $parentLineDiscount);
        $parentVat = (float) $parent->vat;
        $vatPercent = $parentTaxableBase > 0 ? ($parentVat / $parentTaxableBase) * 100 : 0.0;
        $vat = round($taxableBase * (max(0, $vatPercent) / 100), 2);

        $netBeforeCoin = round(
            $grossAmount + $vat - $invoiceDiscount - $specialDiscountAmount - $lineDiscountTotal,
            2,
        );

        $coinsRedeemed = round(max(0, (float) ($data['coins_redeemed'] ?? $parent->coins_redeemed ?? 0)), 2);
        $coinDiscountAmount = 0.0;
        $coinsEarned = 0.0;
        $coinBalanceOffset = $this->coinBalanceOffset($parent);

        if ($parent->customer_id) {
            $settings = $this->coinService->settingsForBranch($branchId);
            $customer = Customer::find($parent->customer_id);

            if ($settings && $settings->isActive() && $customer && ! $customer->is_default) {
                if ($coinsRedeemed > 0) {
                    $coinResult = $this->coinService->resolveForSale(
                        $customer,
                        $settings,
                        $netBeforeCoin,
                        $coinsRedeemed,
                        0,
                        $coinBalanceOffset,
                    );
                    $coinsRedeemed = $coinResult['coins_redeemed'];
                    $coinDiscountAmount = $coinResult['coin_discount_amount'];
                }

                $earnBase = max(0, $netBeforeCoin - $coinDiscountAmount);
                $coinsEarned = $this->coinService->earnCoins($earnBase > 0 ? $earnBase : 0, $settings);
            }
        }

        $netBeforeRoundOff = round($netBeforeCoin - $coinDiscountAmount, 2);
        $roundOffAmount = $this->resolveRoundOffAmount($data, $parent, $netBeforeRoundOff);

        $netAmount = round(max(0, $netBeforeRoundOff - $roundOffAmount), 2);

        return [
            'discount' => $invoiceDiscount,
            'discount_type' => $discountType,
            'discount_value' => $discountValue,
            'vat' => $vat,
            'round_off_amount' => $roundOffAmount,
            'special_discount_id' => $specialResolved['id'] ?? $specialDiscountId,
            'special_discount_amount' => $specialDiscountAmount,
            'promotion_discount_total' => $promotionDiscountTotal,
            'coins_redeemed' => $coinsRedeemed,
            'coin_discount_amount' => $coinDiscountAmount,
            'coins_earned' => $coinsEarned,
            'net_amount' => $netAmount,
            'line_discount_total' => $lineDiscountTotal,
        ];
    }

    public function resolveSettlementAmount(float $netNew, float $oldNet): float
    {
        $diff = abs(round($netNew, 2) - round($oldNet, 2));

        return $diff < 0.01 ? 0.0 : round($diff, 2);
    }

    public function resolveSignedSettlement(float $netNew, float $oldNet): float
    {
        $netNew = round($netNew, 2);
        $oldNet = round($oldNet, 2);

        if (abs($netNew - $oldNet) < 0.01) {
            return 0.0;
        }

        return $netNew > $oldNet
            ? round($netNew - $oldNet, 2)
            : -round($oldNet - $netNew, 2);
    }

    public function resolveOldNetTotal(Sell $parent, float $oldGross): float
    {
        $parent->loadMissing('products');
        $parentCatalogGross = round($parent->products->sum(
            fn ($line) => (float) ($line->original_unit_price ?? $line->unit_price) * (float) $line->quantity
        ), 2);

        if ($parentCatalogGross <= 0) {
            return round($oldGross, 2);
        }

        return round(($oldGross / $parentCatalogGross) * (float) $parent->net_amount, 2);
    }

    public function resolveCustomerAccountEffect(float $netNew, float $oldNet): float
    {
        return round($netNew - $oldNet, 2);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{paid_amount: float, due_amount: float}
     */
    public function resolvePayment(array $data, float $signedSettlement, int $paymentTypeValue): array
    {
        $settlement = round(abs($signedSettlement), 2);
        $isParty = (int) $paymentTypeValue === 5;

        if ($isParty) {
            return [
                'paid_amount' => 0.0,
                'due_amount' => $settlement,
            ];
        }

        $paidAmount = round(min(max(0, (float) ($data['paid_amount'] ?? 0)), $settlement), 2);

        return [
            'paid_amount' => $paidAmount,
            'due_amount' => round(max(0, $settlement - $paidAmount), 2),
        ];
    }

    public function coinBalanceOffset(Sell $parent): float
    {
        return round((float) $parent->coins_redeemed - (float) $parent->coins_earned, 2);
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>
     */
    private function defaultPromoLine(array $item): array
    {
        $catalogPrice = (float) $item['unit_price'];

        return [
            'product_id' => (int) $item['product_id'],
            'variation_id' => ! empty($item['variation_id']) ? (int) $item['variation_id'] : null,
            'quantity' => (int) $item['quantity'],
            'unit_price' => $catalogPrice,
            'original_unit_price' => $catalogPrice,
            'promotion_id' => null,
            'promotion_discount' => 0.0,
            'free_quantity' => 0,
            'promotion_meta' => null,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function normalizedSpecialDiscountId(array $data, Sell $parent): ?int
    {
        if (array_key_exists('special_discount_id', $data)) {
            $id = $data['special_discount_id'];

            return $id === null || $id === '' ? null : (int) $id;
        }

        return $parent->special_discount_id ? (int) $parent->special_discount_id : null;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function resolveRoundOffAmount(array $data, Sell $parent, float $netBeforeRoundOff): float
    {
        if (array_key_exists('round_off_amount', $data)) {
            $roundOff = round(max(0, (float) ($data['round_off_amount'] ?? 0)), 2);

            return round(min($roundOff, max(0, $netBeforeRoundOff)), 2);
        }

        return round(min((float) $parent->round_off_amount, max(0, $netBeforeRoundOff)), 2);
    }
}
