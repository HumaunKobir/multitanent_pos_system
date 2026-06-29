<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ProductExchange;
use App\Models\Promotion;
use App\Models\SaleReturn;
use App\Models\Sell;
use App\Services\CoinService;
use App\Services\PromotionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SaleLookupController extends Controller
{
    public function __construct(
        private PromotionService $promotionService,
        private CoinService $coinService,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        abort_unless(
            $request->user()?->can('inventory.sale-return.create')
            || $request->user()?->can('inventory.product-exchange.create'),
            403,
        );

        $request->validate([
            'invoice' => ['required', 'string'],
        ]);

        $id = $this->resolveSaleId(strtoupper(trim($request->string('invoice')->toString())));

        if ($id === null) {
            return response()->json(['message' => 'Sale not found.'], 404);
        }

        $sell = Sell::query()
            ->ownBranchUser()
            ->sale()
            ->with([
                'customer:id,name,phone',
                'payments:id,sell_id,payment_account_id,amount',
                'products.product:id,name,code,sale_price,discount_price,category_id,brand_id',
                'products.variation:id,variation_data,price,stock',
            ])
            ->find($id);

        if (! $sell) {
            return response()->json(['message' => 'Sale not found.'], 404);
        }

        if (ProductExchange::where('sell_id', $sell->id)->exists()) {
            return response()->json(['message' => 'This sale has already been exchanged.'], 422);
        }

        $returnedQtyByLine = SaleReturn::query()
            ->where('sell_id', $sell->id)
            ->with('products')
            ->get()
            ->flatMap(fn ($return) => $return->products)
            ->groupBy('sell_product_id')
            ->map(fn ($lines) => $lines->sum('quantity'));

        $promotionIds = $sell->products->pluck('promotion_id')->filter()->unique()->values()->all();
        $promotionMap = $promotionIds !== []
            ? Promotion::whereIn('id', $promotionIds)->get()->keyBy('id')
            : collect();

        $items = $sell->products->map(function ($line) use ($returnedQtyByLine, $promotionMap) {
            $sold = (float) $line->quantity;
            $alreadyReturned = (float) ($returnedQtyByLine[$line->id] ?? 0);

            $promotionDetails = null;
            if ($line->promotion_id && $promotionMap->has($line->promotion_id)) {
                $promo = $promotionMap->get($line->promotion_id);
                $promotionDetails = [
                    'type' => $promo->type->value,
                    'min_qty' => $promo->min_qty,
                    'buy_qty' => $promo->buy_qty,
                ];
            }

            return [
                'sell_product_id' => $line->id,
                'product_id' => $line->product_id,
                'product_name' => $line->product?->name,
                'product_code' => $line->product?->code,
                'variation_id' => $line->variation_id,
                'variation_label' => $line->variation?->variation_data['label'] ?? null,
                'category_id' => $line->product?->category_id,
                'brand_id' => $line->product?->brand_id,
                'unit_price' => (float) ($line->original_unit_price ?? $line->unit_price),
                'line_discount' => (float) $line->discount,
                'promotion_discount' => (float) $line->promotion_discount,
                'promotion_id' => $line->promotion_id,
                'promotion_details' => $promotionDetails,
                'sold_quantity' => $sold,
                'returned_quantity' => (int) $alreadyReturned,
                'max_return_quantity' => (int) max(0, $sold - $alreadyReturned),
                'batches' => $line->batches ?? [],
                'sell_price' => $line->variation_id
                    ? (float) ($line->variation?->price ?? $line->unit_price)
                    : (float) ($line->product?->sale_price ?? $line->unit_price),
            ];
        })->values();

        return response()->json([
            'id' => $sell->id,
            'invoice_number' => $sell->invoice_number,
            'customer_id' => $sell->customer_id,
            'customer' => $sell->customer,
            'date' => optional($sell->date)->format('Y-m-d'),
            'has_discount' => $sell->hasAnyDiscount(),
            'has_manual_discount' => $sell->hasManualDiscount(),
            'sell_discounts' => [
                'gross_amount' => (float) $sell->gross_amount,
                'vat' => (float) $sell->vat,
                'line_discount_total' => $sell->lineDiscountTotal(),
                'invoice_discount' => (float) $sell->discount,
                'invoice_discount_type' => $sell->discount_type?->value ?? 'flat',
                'invoice_discount_value' => (float) $sell->discount_value,
                'special_discount_id' => $sell->special_discount_id,
                'special_discount_amount' => (float) $sell->special_discount_amount,
                'promotion_discount_total' => (float) $sell->promotion_discount_total,
                'coin_discount_amount' => (float) $sell->coin_discount_amount,
                'round_off_amount' => (float) $sell->round_off_amount,
                'net_amount' => (float) $sell->net_amount,
                'paid_amount' => (float) $sell->paid_amount,
                'coins_redeemed' => (float) $sell->coins_redeemed,
                'coins_earned' => (float) $sell->coins_earned,
            ],
            'coin_settings' => $this->coinService->settingsPayloadForBranch($sell->branch_id),
            'promotions' => $this->promotionService->activeForBranch($sell->branch_id),
            'payments' => $sell->payments
                ->map(fn ($payment) => [
                    'payment_account_id' => $payment->payment_account_id,
                    'amount' => (float) $payment->amount,
                ])
                ->values(),
            'items' => $items,
        ]);
    }

    private function resolveSaleId(string $invoice): ?int
    {
        if (preg_match('/(\d+)$/', $invoice, $matches)) {
            $sell = Sell::query()->ownBranch()->sale()->whereKey((int) $matches[1])->first();
            if ($sell) {
                return $sell->id;
            }
        }

        if (ctype_digit($invoice)) {
            $sell = Sell::query()->ownBranch()->sale()->whereKey((int) $invoice)->first();

            return $sell?->id;
        }

        return null;
    }
}
