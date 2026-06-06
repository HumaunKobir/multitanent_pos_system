<?php

namespace App\Actions\Checkout;

use App\Enums\OrderStatus;
use App\Models\Customer;
use App\Models\OnlineOrder;
use App\Models\OnlineOrderProduct;
use App\Models\Product;
use App\Support\WebsiteSettings;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class PlaceCodOnlineOrder
{
    /**
     * @param  array<string, array<string, mixed>>  $cart
     * @param  array<string, mixed>  $checkoutData
     */
    public function execute(Customer $customer, array $cart, array $checkoutData): OnlineOrder
    {
        if ($cart === []) {
            throw new InvalidArgumentException('Your cart is empty.');
        }

        $productIds = collect($cart)->pluck('product_id')->unique()->values();
        $existingCount = Product::query()->whereIn('id', $productIds)->count();

        if ($existingCount !== $productIds->count()) {
            throw new InvalidArgumentException('One or more items in your cart are no longer available.');
        }

        $subtotal = 0.0;

        foreach ($cart as $item) {
            $subtotal += (float) $item['price'] * (int) $item['quantity'];
        }

        $deliveryZone = isset($checkoutData['delivery_zone']) ? (int) $checkoutData['delivery_zone'] : null;
        $deliveryCharge = WebsiteSettings::deliveryChargeForCity($deliveryZone);
        $total = $subtotal + $deliveryCharge;

        return DB::transaction(function () use ($customer, $cart, $checkoutData, $subtotal, $deliveryCharge, $total): OnlineOrder {
            $order = OnlineOrder::create([
                'customer_id' => $customer->id,
                'name' => $checkoutData['name'],
                'email' => $checkoutData['email'] ?? null,
                'phone' => $checkoutData['phone'],
                'address' => $checkoutData['address'],
                'payment_method' => 'cod',
                'delivery_charge' => $deliveryCharge,
                'subtotal' => $subtotal,
                'total' => $total,
                'status' => OrderStatus::Pending,
                'payment_status' => 'Pending',
            ]);

            foreach ($cart as $item) {
                $quantity = (int) $item['quantity'];
                $price = (float) $item['price'];

                OnlineOrderProduct::create([
                    'online_order_id' => $order->id,
                    'product_id' => $item['product_id'],
                    'variation_id' => $item['variation_id'] ?? null,
                    'name' => $item['name'],
                    'sku' => $item['sku'] ?? null,
                    'price' => $price,
                    'quantity' => $quantity,
                    'total_price' => $price * $quantity,
                ]);
            }

            return $order->load('products');
        });
    }
}
