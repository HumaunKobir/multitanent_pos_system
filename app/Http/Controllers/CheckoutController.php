<?php

namespace App\Http\Controllers;

use App\Models\OnlineOrder;
use App\Models\OnlineOrderProduct;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CheckoutController extends Controller
{
    public function index(): Response|RedirectResponse
    {
        $cart = session('cart', []);
        if (empty($cart)) {
            return redirect()->route('cart')->with('error', 'কার্ট খালি আছে।');
        }

        return Inertia::render('frontend/checkout', [
            'cart' => array_values($cart),
            'customer' => auth('customer')->user(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $cart = session('cart', []);
        if (empty($cart)) {
            return redirect()->route('cart')->with('error', 'কার্ট খালি আছে।');
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'required|string|max:30',
            'address' => 'required|string',
            'payment_method' => 'required|in:cod,sslcommerz,bkash',
            'city_id' => 'nullable|integer',
            'zone_id' => 'nullable|integer',
            'area_id' => 'nullable|integer',
        ]);

        $subtotal = 0;

        foreach ($cart as $item) {
            $subtotal += $item['price'] * $item['quantity'];
        }

        $cityId = $validated['city_id'] ?? null;
        $deliveryCharge = ($cityId && $cityId == 1) ? 60 : 120;

        $order = OnlineOrder::create([
            'customer_id' => auth('customer')->id(),
            'name' => $validated['name'],
            'email' => $validated['email'] ?? null,
            'phone' => $validated['phone'],
            'address' => $validated['address'],
            'payment_method' => $validated['payment_method'],
            'delivery_charge' => $deliveryCharge,
            'subtotal' => $subtotal,
            'total' => $subtotal + $deliveryCharge,
            'city_id' => $cityId,
            'zone_id' => $validated['zone_id'] ?? null,
            'area_id' => $validated['area_id'] ?? null,
            'status' => 1,
            'payment_status' => 'pending',
        ]);

        foreach ($cart as $item) {
            OnlineOrderProduct::create([
                'online_order_id' => $order->id,
                'product_id' => $item['product_id'],
                'variation_id' => $item['variation_id'] ?? null,
                'name' => $item['name'],
                'sku' => $item['sku'] ?? null,
                'price' => $item['price'],
                'quantity' => $item['quantity'],
                'total_price' => $item['price'] * $item['quantity'],
            ]);
        }

        session()->forget('cart');

        return redirect()->route('order.success', ['id' => $order->id])
            ->with('success', 'অর্ডার সফলভাবে হয়েছে!');
    }

    public function success(int $id): Response
    {
        $order = OnlineOrder::with('products')->findOrFail($id);

        return Inertia::render('frontend/order-success', [
            'order' => $order,
        ]);
    }
}
