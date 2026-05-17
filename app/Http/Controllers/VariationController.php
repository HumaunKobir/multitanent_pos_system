<?php

namespace App\Http\Controllers;

use App\Models\ProductVariation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class VariationController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'variation_data' => ['required', 'array'],
            'price' => ['required', 'numeric', 'min:0'],
            'purchase_price' => ['required', 'numeric', 'min:0'],
            'stock' => ['required', 'numeric', 'min:0'],
        ]);

        $variation = ProductVariation::create([
            'branch_id' => Auth::user()?->branch_id,
            'product_id' => $data['product_id'],
            'variation_data' => $data['variation_data'],
            'price' => $data['price'],
            'purchase_price' => $data['purchase_price'],
            'stock' => $data['stock'],
        ]);

        return response()->json(['success' => true, 'variation' => $variation]);
    }

    public function destroy(ProductVariation $variation): JsonResponse
    {
        if ($variation->stock > 0) {
            return response()->json(['error' => 'Stock আছে, delete করা যাবে না'], 422);
        }

        $variation->delete();

        return response()->json(['success' => true]);
    }
}
