<?php

use App\Models\Customer;
use App\Models\OnlineOrder;
use App\Models\Product;

function cartWithProduct(): array
{
    $product = Product::factory()->create(['sale_price' => 1200, 'discount_price' => 0]);
    $cartKey = $product->id.'-0';

    return [
        $cartKey => [
            'product_id' => $product->id,
            'name' => $product->name,
            'image' => null,
            'price' => 1200.0,
            'quantity' => 1,
            'variation_id' => null,
            'sku' => null,
        ],
    ];
}

test('checkout page redirects when cart is empty', function () {
    $this->get(route('checkout'))
        ->assertRedirect(route('cart'));
});

test('checkout page loads when cart has items', function () {
    session(['cart' => cartWithProduct()]);

    $this->get(route('checkout'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('frontend/checkout'));
});

test('can place order with cod payment', function () {
    session(['cart' => cartWithProduct()]);

    $this->post(route('checkout.store'), [
        'name' => 'রাহেলা বেগম',
        'phone' => '01700000001',
        'address' => 'ঢাকা, বাংলাদেশ',
        'payment_method' => 'cod',
    ])->assertRedirect();

    $this->assertDatabaseHas('online_orders', ['phone' => '01700000001']);
    expect(session('cart'))->toBeNull();
});

test('order calculates dhaka delivery charge as 60', function () {
    session(['cart' => cartWithProduct()]);

    $this->post(route('checkout.store'), [
        'name' => 'Test',
        'phone' => '01700000002',
        'address' => 'Dhaka',
        'payment_method' => 'cod',
        'city_id' => 1,
    ]);

    $this->assertDatabaseHas('online_orders', [
        'phone' => '01700000002',
        'delivery_charge' => 60,
    ]);
});

test('order calculates outside dhaka delivery charge as 120', function () {
    session(['cart' => cartWithProduct()]);

    $this->post(route('checkout.store'), [
        'name' => 'Test',
        'phone' => '01700000003',
        'address' => 'Chittagong',
        'payment_method' => 'cod',
        'city_id' => 5,
    ]);

    $this->assertDatabaseHas('online_orders', [
        'phone' => '01700000003',
        'delivery_charge' => 120,
    ]);
});

test('checkout form requires name, phone, address, and payment method', function () {
    session(['cart' => cartWithProduct()]);

    $this->post(route('checkout.store'), [])
        ->assertSessionHasErrors(['name', 'phone', 'address', 'payment_method']);
});

test('checkout redirects to empty cart when cart is empty on post', function () {
    $this->post(route('checkout.store'), [
        'name' => 'Test',
        'phone' => '01700000000',
        'address' => 'Dhaka',
        'payment_method' => 'cod',
    ])->assertRedirect(route('cart'));
});

test('order success page loads', function () {
    $product = Product::factory()->create(['sale_price' => 1000, 'discount_price' => 0]);

    $order = OnlineOrder::create([
        'name' => 'Test',
        'phone' => '01700000000',
        'address' => 'Dhaka',
        'payment_method' => 'cod',
        'delivery_charge' => 60,
        'subtotal' => 1000,
        'total' => 1060,
        'status' => 1,
        'payment_status' => 'pending',
    ]);

    $this->get(route('order.success', $order->id))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('frontend/order-success'));
});

test('logged in customer order is linked to customer', function () {
    $customer = Customer::factory()->create();
    session(['cart' => cartWithProduct()]);

    $this->actingAs($customer, 'customer')
        ->post(route('checkout.store'), [
            'name' => $customer->name,
            'phone' => $customer->phone,
            'address' => 'Dhaka',
            'payment_method' => 'cod',
        ]);

    $this->assertDatabaseHas('online_orders', ['customer_id' => $customer->id]);
});
