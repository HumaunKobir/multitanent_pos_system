<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ProductExchange;
use App\Models\SaleReturn;
use App\Models\Sell;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SaleLookupController extends Controller
{
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
            ->ownBranch()
            ->sale()
            ->with([
                'customer:id,name,phone',
                'products.product:id,name,code,sale_price,discount_price',
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

        $items = $sell->products->map(function ($line) use ($returnedQtyByLine) {
            $sold = (float) $line->quantity;
            $alreadyReturned = (float) ($returnedQtyByLine[$line->id] ?? 0);

            return [
                'sell_product_id' => $line->id,
                'product_id' => $line->product_id,
                'product_name' => $line->product?->name,
                'product_code' => $line->product?->code,
                'variation_id' => $line->variation_id,
                'variation_label' => $line->variation?->variation_data['label'] ?? null,
                'unit_price' => (float) $line->unit_price,
                'sold_quantity' => (int) $sold,
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
            'discount' => (float) $sell->discount,
            'has_discount' => (float) $sell->discount > 0,
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
