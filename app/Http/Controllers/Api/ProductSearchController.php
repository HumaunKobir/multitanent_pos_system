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

        $branchId = Auth::user()?->branch_id ?? Branch::resolveMainBranchId();
        $mainBranchId = Branch::resolveMainBranchId();

        $products = Product::forPurchase()
            ->active()
            ->with([
                'variations' => fn ($q) => $q
                    ->where(function ($query) use ($branchId, $mainBranchId) {
                        if ($branchId === $mainBranchId) {
                            $query->where('branch_id', $branchId)
                                ->orWhereNull('branch_id');
                        } else {
                            $query->where('branch_id', $branchId);
                        }
                    })
                    ->select(['id', 'product_id', 'branch_id', 'sku', 'variation_data', 'purchase_price', 'price', 'stock']),
                'batches' => fn ($q) => $q->atBranchWarehouse($branchId)
                    ->select(['id', 'product_id', 'branch_id', 'available']),
            ])
            ->when($request->search, fn ($q, $s) => $q->where(function ($q) use ($s, $branchId) {
                $q->where('name', 'like', "%{$s}%")
                    ->orWhere('code', 'like', "%{$s}%")
                    ->orWhereHas('variations', fn ($variationQuery) => $variationQuery
                        ->where('branch_id', $branchId)
                        ->where('sku', 'like', "%{$s}%"))
                    ->orWhereHas('barcodes', fn ($barcodeQuery) => $barcodeQuery
                        ->where('branch_id', $branchId)
                        ->where('code', 'like', "%{$s}%"));
            }))
            ->latest()
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
            'stock' => (float) $product->batches->sum('available'),
            'variations' => $product->variations->map(fn ($v) => [
                'id' => $v->id,
                'label' => $v->variation_data['label'] ?? $v->sku,
                'sku' => $v->sku,
                'purchase_price' => $v->purchase_price,
                'sale_price' => $v->price,
                'stock' => (float) $v->stock,
            ])->values(),
        ]));
    }

    public function forSell(Request $request): JsonResponse
    {
        $this->authorize('inventory.sell.create');

        $request->validate([
            'category_id' => ['nullable', 'integer', Rule::exists('categories', 'id')],
        ]);

        $branchId = Auth::user()?->branch_id ?? Branch::resolveMainBranchId();
        $mainBranchId = Branch::resolveMainBranchId();
        $hasSearch = filled($request->search);

        $limit = $hasSearch ? 15 : 48;

        $products = Product::forPurchase()
            ->active()
            ->with([
                'category:id,name',
                'variations' => fn ($q) => $q
                    ->where(function ($query) use ($branchId, $mainBranchId) {
                        if ($branchId === $mainBranchId) {
                            $query->where('branch_id', $branchId)
                                ->orWhereNull('branch_id');
                        } else {
                            $query->where('branch_id', $branchId);
                        }
                    })
                    ->select(['id', 'product_id', 'branch_id', 'sku', 'variation_data', 'price', 'stock']),
                'batches' => fn ($q) => $q->atBranchWarehouse($branchId)
                    ->where('available', '>', 0)
                    ->select(['id', 'product_id', 'branch_id', 'available']),
            ])
            ->when($request->search, fn ($q, $s) => $q->where(function ($q) use ($s, $branchId) {
                $q->where('name', 'like', "%{$s}%")
                    ->orWhere('code', 'like', "%{$s}%")
                    ->orWhereHas('variations', fn ($variationQuery) => $variationQuery
                        ->where('branch_id', $branchId)
                        ->where('sku', 'like', "%{$s}%"))
                    ->orWhereHas('barcodes', fn ($barcodeQuery) => $barcodeQuery
                        ->where('branch_id', $branchId)
                        ->where('code', 'like', "%{$s}%"));
            }))
            ->when($request->category_id, fn ($q, $id) => $q->where('category_id', $id))
            ->limit($limit)
            ->get(['id', 'name', 'code', 'category_id', 'brand_id', 'branch_id', 'sale_price', 'discount_price', 'image']);

        return response()->json($products->map(function (Product $product) {
            $basePrice = (float) $product->sale_price;
            $effectivePrice = $product->discount_price > 0 ? (float) $product->discount_price : $basePrice;

            return [
                'id' => $product->id,
                'name' => $product->name,
                'code' => $product->code,
                'category_id' => $product->category_id,
                'brand_id' => $product->brand_id,
                'category_name' => $product->category?->name,
                'sale_price' => $effectivePrice,
                'original_sale_price' => $basePrice,
                'catalog_price' => $effectivePrice,
                'image' => StorageUrl::public($product->image),
                'has_variations' => $product->variations->isNotEmpty(),
                'stock' => (float) $product->batches->sum('available'),
                'variations' => $product->variations
                    ->map(fn ($v) => [
                        'id' => $v->id,
                        'label' => $v->variation_data['label'] ?? $v->sku,
                        'sku' => $v->sku,
                        'sale_price' => (float) $v->price,
                        'stock' => (float) $v->stock,
                    ])
                    ->values(),
            ];
        })->sortByDesc(function (array $p) {
            if ($p['stock'] > 0) {
                return 1;
            }

            return collect($p['variations'])->contains(fn ($v) => $v['stock'] > 0) ? 1 : 0;
        })->values());
    }

    public function forDistribution(Request $request): JsonResponse
    {
        $this->authorize('inventory.stock-distribution.create');

        abort_unless(
            Auth::user()?->usesAdminPanel(),
            403
        );

        $mainBranchId = Branch::resolveMainBranchId();
        $hasSearch = filled($request->search);

        $products = Product::query()
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
            ->when($request->search, fn ($q, $s) => $q->where(function ($q) use ($s, $mainBranchId) {
                $q->where('name', 'like', "%{$s}%")
                    ->orWhere('code', 'like', "%{$s}%")
                    ->orWhereHas('variations', fn ($variationQuery) => $variationQuery
                        ->where('branch_id', $mainBranchId)
                        ->where('sku', 'like', "%{$s}%"))
                    ->orWhereHas('barcodes', fn ($barcodeQuery) => $barcodeQuery
                        ->where('branch_id', $mainBranchId)
                        ->where('code', 'like', "%{$s}%"));
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
