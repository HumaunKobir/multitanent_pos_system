<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Branch;
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

        $products = Product::forPurchase()
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
            $branchVariations = $product->variations
                ->when($branchId !== null, fn ($variations) => $variations->filter(
                    fn ($variation) => $variation->branch_id === $branchId || $variation->branch_id === null
                ));

            return [
                'id' => $product->id,
                'name' => $product->name,
                'code' => $product->code,
                'sale_price' => $product->discount_price > 0 ? (float) $product->discount_price : (float) $product->sale_price,
                'image' => $product->image,
                'has_variations' => $branchVariations->isNotEmpty(),
                'stock' => (float) $product->batches
                    ->when($branchId !== null, fn ($batches) => $batches->filter(
                        fn ($batch) => $batch->branch_id === $branchId || $batch->branch_id === null
                    ))
                    ->sum('available'),
                'variations' => $branchVariations
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

    public function forDistribution(Request $request): JsonResponse
    {
        $this->authorize('inventory.stock-distribution.create');

        abort_unless(
            Branch::isMainBranch(Auth::user()?->branch_id) || Auth::user()?->isSuperAdmin(),
            403
        );

        $mainBranchId = Branch::MAIN_BRANCH_ID;

        $products = Product::forPurchase()
            ->active()
            ->with([
                'variations:id,product_id,branch_id,sku,variation_data,price,stock',
                'batches' => fn ($q) => $q->atBranchWarehouse($mainBranchId)
                    ->where('available', '>', 0)
                    ->select(['id', 'product_id', 'branch_id', 'available']),
            ])
            ->when($request->search, fn ($q, $s) => $q->where(function ($q) use ($s) {
                $q->where('name', 'like', "%{$s}%")
                    ->orWhere('code', 'like', "%{$s}%");
            }))
            ->limit(15)
            ->get(['id', 'name', 'code', 'sale_price', 'discount_price', 'image']);

        return response()->json($products->map(function (Product $product) use ($mainBranchId) {
            $mainVariations = $product->variations->filter(
                fn ($v) => $v->branch_id === $mainBranchId || $v->branch_id === null
            );

            return [
                'id' => $product->id,
                'name' => $product->name,
                'code' => $product->code,
                'has_variations' => $mainVariations->isNotEmpty(),
                'stock' => (float) $product->batches->sum('available'),
                'variations' => $mainVariations
                    ->map(fn ($v) => [
                        'id' => $v->id,
                        'label' => $v->variation_data['label'] ?? $v->sku,
                        'stock' => (float) $v->stock,
                    ])
                    ->values(),
            ];
        })->filter(function (array $product) {
            if ((float) $product['stock'] > 0) {
                return true;
            }

            return collect($product['variations'])->contains(fn ($v) => (float) $v['stock'] > 0);
        })->values());
    }
}
