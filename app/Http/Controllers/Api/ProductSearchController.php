<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ProductSearchController extends Controller
{
    public function forPurchase(Request $request): JsonResponse
    {
        $this->authorize('inventory.purchase.create');

        $products = Product::forPurchase()
            ->active()
            ->with('variations:id,product_id,sku,variation_data,purchase_price,price,stock')
            ->when($request->search, fn ($q, $s) => $q->where(function ($q) use ($s) {
                $q->where('name', 'like', "%{$s}%")
                    ->orWhere('code', 'like', "%{$s}%");
            }))
            ->limit(15)
            ->get(['id', 'name', 'code', 'purchase_price', 'sale_price', 'image']);

        return response()->json($products->map(fn (Product $product) => [
            'id' => $product->id,
            'name' => $product->name,
            'code' => $product->code,
            'purchase_price' => $product->purchase_price,
            'sale_price' => $product->sale_price,
            'image' => $product->image,
            'has_variations' => $product->variations->isNotEmpty(),
            'variations' => $product->variations->map(fn ($v) => [
                'id' => $v->id,
                'label' => $v->variation_data['label'] ?? $v->sku,
                'purchase_price' => $v->purchase_price,
                'sale_price' => $v->price,
                'stock' => $v->stock,
            ]),
        ]));
    }

    public function forSell(Request $request): JsonResponse
    {
        $this->authorize('inventory.sell.create');

        $branchId = Auth::user()?->branch_id;

        $products = Product::ownBranch()
            ->active()
            ->with([
                'variations:id,product_id,branch_id,sku,variation_data,price,stock',
                'batches' => fn ($q) => $q->where('available', '>', 0)
                    ->select(['id', 'product_id', 'branch_id', 'available']),
            ])
            ->when($request->search, fn ($q, $s) => $q->where(function ($q) use ($s) {
                $q->where('name', 'like', "%{$s}%")
                    ->orWhere('code', 'like', "%{$s}%");
            }))
            ->limit(15)
            ->get(['id', 'name', 'code', 'branch_id', 'sale_price', 'discount_price', 'image']);

        return response()->json($products->map(function (Product $product) use ($branchId) {
            $stockBranchId = $product->resolveStockBranchId($branchId);

            return [
                'id' => $product->id,
                'name' => $product->name,
                'code' => $product->code,
                'sale_price' => $product->discount_price > 0 ? (float) $product->discount_price : (float) $product->sale_price,
                'image' => $product->image,
                'has_variations' => $product->variations->isNotEmpty(),
                'stock' => (float) $product->batches
                    ->when($stockBranchId !== null, fn ($batches) => $batches->where('branch_id', $stockBranchId))
                    ->sum('available'),
                'variations' => $product->variations
                    ->when($stockBranchId !== null, fn ($variations) => $variations->where('branch_id', $stockBranchId))
                    ->map(fn ($v) => [
                        'id' => $v->id,
                        'label' => $v->variation_data['label'] ?? $v->sku,
                        'sale_price' => (float) $v->price,
                        'stock' => (float) $v->stock,
                    ])
                    ->values(),
            ];
        }));
    }
}
