<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductVariation;
use App\Support\StorageUrl;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CartController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('frontend/cart', [
            'cart' => session('cart', []),
        ]);
    }

    public function json(): JsonResponse
    {
        $cart = session('cart', []);

        return response()->json([
            'cart' => $cart,
            'cart_count' => count($cart),
        ]);
    }

    public function addToCart(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'product_id' => 'required|exists:products,id',
            'quantity' => 'required|integer|min:1',
            'variation_id' => 'nullable|exists:product_variations,id',
            'tailor_service' => 'nullable|string|in:standard,tailor',
            'tailor_price' => 'nullable|numeric|min:0',
            'tailormeasurement' => 'nullable|array',
        ]);

        $product = Product::query()->forStorefront()->findOrFail($validated['product_id']);
        $price = $product->discount_price > 0 ? $product->discount_price : $product->sale_price;
        $sku = null;
        $tailorSuffix = '';

        if (! empty($validated['variation_id'])) {
            $variation = ProductVariation::findOrFail($validated['variation_id']);
            $price = $variation->price;
            $sku = $variation->sku;
        }

        if (($validated['tailor_service'] ?? null) === 'tailor') {
            $price += (float) ($validated['tailor_price'] ?? $product->tailor_price ?? 0);
            $tailorSuffix = '_tailor';
        }

        $cartKey = $validated['product_id'].'-'.($validated['variation_id'] ?? '0').$tailorSuffix;
        $cart = session('cart', []);

        if (isset($cart[$cartKey])) {
            $cart[$cartKey]['quantity'] += $validated['quantity'];
        } else {
            $cart[$cartKey] = [
                'product_id' => $product->id,
                'name' => $product->name,
                'image' => StorageUrl::public($product->image),
                'price' => (float) $price,
                'base_price' => (float) ($product->discount_price > 0 ? $product->discount_price : $product->sale_price),
                'quantity' => $validated['quantity'],
                'variation_id' => $validated['variation_id'] ?? null,
                'sku' => $sku,
                'tailor_service' => $validated['tailor_service'] ?? null,
                'tailor_price' => (float) ($validated['tailor_price'] ?? 0),
                'tailormeasurement' => $validated['tailormeasurement'] ?? null,
            ];
        }

        session(['cart' => $cart]);

        return response()->json([
            'message' => 'Product added to cart',
            'cart' => $cart,
            'cart_count' => count($cart),
        ]);
    }

    public function update(Request $request, string $cartKey): JsonResponse|RedirectResponse
    {
        $validated = $request->validate([
            'quantity' => 'required|integer|min:1',
        ]);

        $cart = session('cart', []);
        if (isset($cart[$cartKey])) {
            $cart[$cartKey]['quantity'] = $validated['quantity'];
            session(['cart' => $cart]);
        }

        if ($request->wantsJson()) {
            return response()->json([
                'cart' => $cart,
                'cart_count' => count($cart),
            ]);
        }

        return back();
    }

    public function remove(Request $request, string $cartKey): JsonResponse|RedirectResponse
    {
        $cart = session('cart', []);
        unset($cart[$cartKey]);
        session(['cart' => $cart]);

        if ($request->wantsJson()) {
            return response()->json([
                'cart' => $cart,
                'cart_count' => count($cart),
            ]);
        }

        return back();
    }

    public function clear(): RedirectResponse
    {
        session()->forget('cart');

        return back();
    }
}
