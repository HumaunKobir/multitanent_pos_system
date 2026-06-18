<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Product;
use App\Support\StorageUrl;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

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

        $request->validate([
            'category_id' => ['nullable', 'integer', Rule::exists('categories', 'id')],
        ]);

        $branchId = Auth::user()?->branch_id;
        $hasSearch = filled($request->search);

        $limit = $hasSearch ? 15 : 48;

        $products = Product::forPurchase()
            ->active()
            ->with([
                'category:id,name',
                'variations:id,product_id,branch_id,sku,variation_data,price,stock',
                'batches' => fn ($q) => $q->where('available', '>', 0)
                    ->select(['id', 'product_id', 'branch_id', 'available']),
            ])
            ->when($request->search, fn ($q, $s) => $q->where(function ($q) use ($s) {
                $q->where('name', 'like', "%{$s}%")
                    ->orWhere('code', 'like', "%{$s}%");
            }))
            ->when($request->category_id, fn ($q, $id) => $q->where('category_id', $id))
            ->limit($limit)
            ->get(['id', 'name', 'code', 'category_id', 'branch_id', 'sale_price', 'discount_price', 'image']);

        return response()->json($products->map(function (Product $product) use ($branchId) {
            $branchVariations = $product->variations
                ->when($branchId !== null, fn ($variations) => $variations->filter(
                    fn ($variation) => $variation->branch_id === $branchId
                ));

            return [
                'id' => $product->id,
                'name' => $product->name,
                'code' => $product->code,
                'category_id' => $product->category_id,
                'category_name' => $product->category?->name,
                'sale_price' => $product->discount_price > 0 ? (float) $product->discount_price : (float) $product->sale_price,
                'image' => StorageUrl::public($product->image),
                'has_variations' => $branchVariations->isNotEmpty(),
                'stock' => (float) $product->batches
                    ->when($branchId !== null, fn ($batches) => $batches->filter(
                        fn ($batch) => $batch->branch_id === $branchId
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
            Auth::user()?->isSuperAdmin(),
            403
        );

        $mainBranchId = Branch::resolveMainBranchId();
        $hasSearch = filled($request->search);

        $products = Product::forPurchase()
            ->active()
            ->atBranch($mainBranchId)
            ->where(function ($query) use ($mainBranchId) {
                $query->whereHas('batches', fn ($q) => $q
                    ->atBranchWarehouse($mainBranchId)
                    ->where('available', '>', 0))
                    ->orWhereHas('variations', fn ($q) => $q
                        ->where('branch_id', $mainBranchId)
                        ->where('stock', '>', 0));
            })
            ->with([
                'variations' => fn ($q) => $q
                    ->where('branch_id', $mainBranchId)
                    ->where('stock', '>', 0)
                    ->select(['id', 'product_id', 'branch_id', 'sku', 'variation_data', 'price', 'stock']),
                'batches' => fn ($q) => $q->atBranchWarehouse($mainBranchId)
                    ->where('available', '>', 0)
                    ->select(['id', 'product_id', 'branch_id', 'available']),
            ])
            ->when($request->search, fn ($q, $s) => $q->where(function ($q) use ($s) {
                $q->where('name', 'like', "%{$s}%")
                    ->orWhere('code', 'like', "%{$s}%");
            }))
            ->orderBy('name')
            ->when($hasSearch, fn ($q) => $q->limit(50))
            ->get(['id', 'name', 'code', 'sale_price', 'discount_price', 'image']);

        return response()->json($products->map(function (Product $product) {
            $mainVariations = $product->variations;

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
