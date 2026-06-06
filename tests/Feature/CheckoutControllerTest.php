<?php

use App\Enums\OrderStatus;
use App\Models\Customer;
use App\Models\OnlineOrder;
use App\Models\Product;
use App\Support\WebsiteSettings;

function uniqueCheckoutPhone(): string
{
    return fake()->unique()->numerify('017########');
}

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

test('guest cannot place order without logging in', function () {
    session(['cart' => cartWithProduct()]);

    $this->post(route('checkout.store'), [
        'name' => 'Guest User',
        'phone' => '01700000001',
        'address' => 'Dhaka',
        'payment_method' => 'cod',
    ])
        ->assertRedirect()
        ->assertSessionHas('error', 'Please log in to place your order.');
});

test('can place cod order and create order products', function () {
    $customer = Customer::factory()->create();
    $cart = cartWithProduct();
    $productId = reset($cart)['product_id'];
    $phone = uniqueCheckoutPhone();
    session(['cart' => $cart]);

    $this->actingAs($customer, 'customer')
        ->post(route('checkout.store'), [
            'name' => 'রাহেলা বেগম',
            'phone' => $phone,
            'address' => 'ঢাকা, বাংলাদেশ',
            'payment_method' => 'cod',
            'delivery_zone' => 1,
        ])->assertRedirect();

    $order = OnlineOrder::query()->where('phone', $phone)->latest('id')->first();
    $expectedDelivery = WebsiteSettings::deliveryChargeForCity(1);

    expect($order)->not->toBeNull()
        ->and($order->customer_id)->toBe($customer->id)
        ->and($order->payment_method)->toBe('cod')
        ->and($order->payment_status)->toBe('Pending')
        ->and($order->status)->toBe(OrderStatus::Pending)
        ->and((float) $order->delivery_charge)->toBe($expectedDelivery)
        ->and((float) $order->subtotal)->toBe(1200.0)
        ->and((float) $order->total)->toBe(1200.0 + $expectedDelivery);

    expect($order->products)->toHaveCount(1);
    expect($order->products->first())
        ->product_id->toBe($productId)
        ->quantity->toBe(1)
        ->and((float) $order->products->first()->total_price)->toBe(1200.0);

    expect(session('cart'))->toBeNull();
});

test('sslcommerz checkout is not available yet', function () {
    $customer = Customer::factory()->create();
    $phone = uniqueCheckoutPhone();
    session(['cart' => cartWithProduct()]);

    $this->actingAs($customer, 'customer')
        ->post(route('checkout.store'), [
            'name' => 'Karim Ahmed',
            'phone' => $phone,
            'address' => 'Dhaka',
            'payment_method' => 'sslcommerz',
        ])
        ->assertRedirect()
        ->assertSessionHas('error', 'Online payment is coming soon. Please choose Cash on Delivery.');

    $this->assertDatabaseMissing('online_orders', ['phone' => $phone]);
    expect(session('cart'))->not->toBeNull();
});

test('order calculates inside dhaka delivery charge as 60', function () {
    $customer = Customer::factory()->create();
    session(['cart' => cartWithProduct()]);

    $this->actingAs($customer, 'customer')
        ->post(route('checkout.store'), [
            'name' => 'Test',
            'phone' => '01700000002',
            'address' => 'Dhaka',
            'payment_method' => 'cod',
            'delivery_zone' => 1,
        ]);

    $this->assertDatabaseHas('online_orders', [
        'phone' => '01700000002',
        'delivery_charge' => 60,
    ]);
});

test('order calculates outside dhaka delivery charge as 120', function () {
    $customer = Customer::factory()->create();
    session(['cart' => cartWithProduct()]);

    $this->actingAs($customer, 'customer')
        ->post(route('checkout.store'), [
            'name' => 'Test',
            'phone' => '01700000003',
            'address' => 'Chittagong',
            'payment_method' => 'cod',
            'delivery_zone' => 2,
        ]);

    $this->assertDatabaseHas('online_orders', [
        'phone' => '01700000003',
        'delivery_charge' => 120,
    ]);
});

test('checkout form requires name, phone, address, and payment method', function () {
    $customer = Customer::factory()->create();
    session(['cart' => cartWithProduct()]);

    $this->actingAs($customer, 'customer')
        ->post(route('checkout.store'), [])
        ->assertSessionHasErrors(['name', 'phone', 'address', 'payment_method']);
});

test('checkout redirects to empty cart when cart is empty on post', function () {
    $customer = Customer::factory()->create();

    $this->actingAs($customer, 'customer')
        ->post(route('checkout.store'), [
            'name' => 'Test',
            'phone' => '01700000000',
            'address' => 'Dhaka',
            'payment_method' => 'cod',
        ])->assertRedirect(route('cart'));
});

test('order success page loads', function () {
    Product::factory()->create(['sale_price' => 1000, 'discount_price' => 0]);

    $order = OnlineOrder::create([
        'name' => 'Test',
        'phone' => '01700000000',
        'address' => 'Dhaka',
        'payment_method' => 'cod',
        'delivery_charge' => 60,
        'subtotal' => 1000,
        'total' => 1060,
        'status' => OrderStatus::Pending,
        'payment_status' => 'Pending',
    ]);

    $this->get(route('checkout.success', $order->id))
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

test('full checkout flow from cart add through success page', function () {
    $customer = Customer::factory()->create([
        'password' => bcrypt('password123'),
    ]);
    $product = Product::factory()->create([
        'sale_price' => 1500,
        'discount_price' => 0,
    ]);
    $phone = uniqueCheckoutPhone();

    $this->post(route('customer.login'), [
        'email' => $customer->email,
        'password' => 'password123',
    ])->assertRedirect();

    $this->assertAuthenticatedAs($customer, 'customer');

    $this->postJson(route('cart.add'), [
        'product_id' => $product->id,
        'quantity' => 2,
    ])->assertSuccessful()
        ->assertJsonPath('cart_count', 2);

    $this->get(route('checkout'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('frontend/checkout')
            ->has('cart', 1)
            ->where('customer.id', $customer->id));

    $response = $this->post(route('checkout.store'), [
        'name' => $customer->name,
        'email' => $customer->email,
        'phone' => $phone,
        'address' => 'House 12, Road 5, Banani, Dhaka',
        'payment_method' => 'cod',
        'delivery_zone' => '1',
    ]);

    $order = OnlineOrder::query()->where('phone', $phone)->latest('id')->first();

    expect($order)->not->toBeNull();

    $response->assertRedirect(route('checkout.success', ['order' => $order->id]))
        ->assertSessionHas('success', 'Order placed successfully!');

    $this->get(route('checkout.success', ['order' => $order->id]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('frontend/order-success')
            ->where('order.id', $order->id)
            ->where('order.phone', $phone)
            ->has('order.products', 1));

    expect($order->fresh())
        ->customer_id->toBe($customer->id)
        ->payment_method->toBe('cod')
        ->and($order->products->first()->product_id)->toBe($product->id)
        ->and($order->products->first()->quantity)->toBe(2)
        ->and((float) $order->subtotal)->toBe(3000.0);

    expect(session('cart'))->toBeNull();
});
