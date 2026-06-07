<?php

namespace App\Http\Controllers;

use App\Actions\Checkout\InitiateSslCommerzPayment;
use App\Actions\Checkout\PlaceCodOnlineOrder;
use App\Http\Requests\StoreCheckoutRequest;
use App\Models\OnlineOrder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response as HttpResponse;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;

class CheckoutController extends Controller
{
    public function index(): Response|RedirectResponse
    {
        $cart = session('cart', []);
        if (empty($cart)) {
            return redirect()->route('cart')->with('error', 'Your cart is empty.');
        }

        return Inertia::render('frontend/checkout', [
            'cart' => array_values($cart),
            'customer' => auth('customer')->user(),
        ]);
    }

    public function store(
        StoreCheckoutRequest $request,
        PlaceCodOnlineOrder $placeCodOnlineOrder,
        InitiateSslCommerzPayment $initiateSslCommerzPayment,
    ): RedirectResponse|HttpResponse {
        if (! auth('customer')->check()) {
            return back()->with('error', 'Please log in to place your order.');
        }

        $cart = session('cart', []);
        if (empty($cart)) {
            return redirect()->route('cart')->with('error', 'Your cart is empty.');
        }

        $customer = auth('customer')->user();
        $checkoutData = $request->validated();

        try {
            if ($checkoutData['payment_method'] === 'sslcommerz') {
                return $initiateSslCommerzPayment->execute($customer, $cart, $checkoutData);
            }

            $order = $placeCodOnlineOrder->execute($customer, $cart, $checkoutData);
        } catch (InvalidArgumentException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        session()->forget('cart');

        return redirect()->route('checkout.success', ['order' => $order->id])
            ->with('success', 'Order placed successfully!');
    }

    public function success(int $id): Response
    {
        $order = OnlineOrder::with('products')->findOrFail($id);

        return Inertia::render('frontend/order-success', [
            'order' => $order,
        ]);
    }
}
