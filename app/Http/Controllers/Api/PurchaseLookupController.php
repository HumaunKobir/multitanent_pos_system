<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Purchase;
use App\Models\PurchaseReturn;
use App\Models\StockDistribution;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PurchaseLookupController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $this->authorize('inventory.purchase-return.create');

        $request->validate([
            'invoice' => ['required', 'string'],
        ]);

        $id = $this->resolvePurchaseId(strtoupper(trim($request->string('invoice')->toString())));

        if ($id === null) {
            return response()->json(['message' => 'Purchase not found.'], 404);
        }

        $purchase = Purchase::query()
            ->ownBranchUser()
            ->purchase()
            ->with([
                'supplier:id,name,company_name,phone',
                'purchaseProducts.product:id,name,code',
                'purchaseProducts.variation:id,variation_data,stock',
            ])
            ->find($id);

        if (! $purchase) {
            return response()->json(['message' => 'Purchase not found.'], 404);
        }

        $branchesHoldingStock = StockDistribution::branchesHoldingReceivedStockForPurchase($purchase->id);

        if ($branchesHoldingStock !== []) {
            return response()->json([
                'message' => 'This purchase cannot be returned yet. Its stock was received by '
                    .implode(', ', $branchesHoldingStock)
                    .'. That branch must return the stock to the main warehouse before this purchase can be returned.',
            ], 422);
        }

        $returnedQtyByLine = PurchaseReturn::query()
            ->where('purchase_id', $purchase->id)
            ->with('products')
            ->get()
            ->flatMap(fn ($return) => $return->products)
            ->groupBy('purchase_product_id')
            ->map(fn ($lines) => $lines->sum('quantity'));

        $items = $purchase->purchaseProducts->map(function ($line) use ($returnedQtyByLine) {
            $purchased = (float) $line->quantity;
            $alreadyReturned = (float) ($returnedQtyByLine[$line->id] ?? 0);

            return [
                'purchase_product_id' => $line->id,
                'product_id' => $line->product_id,
                'product_name' => $line->product?->name,
                'product_code' => $line->product?->code,
                'variation_id' => $line->variation_id,
                'variation_label' => $line->variation?->variation_data['label'] ?? null,
                'unit_price' => (float) $line->unit_price,
                'purchased_quantity' => (int) $purchased,
                'returned_quantity' => (int) $alreadyReturned,
                'max_return_quantity' => (int) max(0, $purchased - $alreadyReturned),
                'batches' => $line->batches ?? [],
            ];
        })->values();

        $vatPercent = (float) $purchase->gross_amount > 0
            ? ((float) $purchase->vat / (float) $purchase->gross_amount) * 100
            : 0;

        $netAmount = (float) $purchase->gross_amount + (float) $purchase->vat - (float) $purchase->discount;

        return response()->json([
            'id' => $purchase->id,
            'invoice_number' => $purchase->invoice_number,
            'supplier_id' => $purchase->supplier_id,
            'supplier' => $purchase->supplier,
            'date' => optional($purchase->date)->format('Y-m-d'),
            'payment_type' => $purchase->payment_type?->value,
            'gross_amount' => (float) $purchase->gross_amount,
            'discount' => (float) $purchase->discount,
            'vat' => (float) $purchase->vat,
            'vat_percent' => $vatPercent,
            'net_amount' => $netAmount,
            'paid_amount' => (float) $purchase->paid_amount,
            'due_amount' => (float) $purchase->due_amount,
            'items' => $items,
        ]);
    }

    private function resolvePurchaseId(string $invoice): ?int
    {
        if (preg_match('/(\d+)$/', $invoice, $matches)) {
            $purchase = Purchase::query()->ownBranch()->purchase()->whereKey((int) $matches[1])->first();
            if ($purchase) {
                return $purchase->id;
            }
        }

        if (ctype_digit($invoice)) {
            $purchase = Purchase::query()->ownBranch()->purchase()->whereKey((int) $invoice)->first();

            return $purchase?->id;
        }

        return null;
    }
}
