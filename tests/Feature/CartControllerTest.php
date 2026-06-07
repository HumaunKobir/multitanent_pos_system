<?php

use App\Models\Product;

test('cart page loads', function () {
    $this->get(route('cart'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('frontend/cart'));
});

test('can add product to cart', function () {
    $product = Product::factory()->create(['sale_price' => 1000, 'discount_price' => 0]);

    $this->post(route('cart.add'), [
        'product_id' => $product->id,
        'quantity' => 2,
    ])->assertOk()
        ->assertJson(['cart_count' => 1]);

    expect(session('cart', []))->toHaveCount(1);
});

test('cart count reflects line items not total quantity', function () {
    $product = Product::factory()->create(['sale_price' => 1000, 'discount_price' => 0]);

    $this->post(route('cart.add'), [
        'product_id' => $product->id,
        'quantity' => 100,
    ])->assertOk()
        ->assertJson(['cart_count' => 1]);

    expect(session('cart', []))->toHaveCount(1);
});

test('adding same product increments quantity', function () {
    $product = Product::factory()->create(['sale_price' => 1000, 'discount_price' => 0]);

    $this->post(route('cart.add'), ['product_id' => $product->id, 'quantity' => 1]);
    $this->post(route('cart.add'), ['product_id' => $product->id, 'quantity' => 2]);

    $cart = session('cart', []);
    $item = reset($cart);
    expect($item['quantity'])->toBe(3);
});

test('add to cart uses discount price when set', function () {
    $product = Product::factory()->create(['sale_price' => 1000, 'discount_price' => 750]);

    $this->post(route('cart.add'), ['product_id' => $product->id, 'quantity' => 1]);

    $cart = session('cart', []);
    $item = reset($cart);
    expect($item['price'])->toBe(750.0);
});

test('add to cart requires product_id and quantity', function () {
    $this->post(route('cart.add'), [])
        ->assertSessionHasErrors(['product_id', 'quantity']);
});

test('add to cart rejects non-existent product', function () {
    $this->post(route('cart.add'), ['product_id' => 9999, 'quantity' => 1])
        ->assertSessionHasErrors(['product_id']);
});

test('can update cart item quantity', function () {
    $product = Product::factory()->create(['sale_price' => 500, 'discount_price' => 0]);
    $cartKey = $product->id.'-0';
    session(['cart' => [
        $cartKey => [
            'product_id' => $product->id,
            'name' => $product->name,
            'price' => 500.0,
            'quantity' => 1,
            'image' => null,
            'variation_id' => null,
            'sku' => null,
            'tailor_service' => false,
            'tailor_price' => 0.0,
            'tailormeasurement' => null,
        ],
    ]]);

    $this->patch(route('cart.update', $cartKey), ['quantity' => 3])
        ->assertRedirect();

    expect(session("cart.$cartKey.quantity"))->toBe(3);
});

test('can remove item from cart', function () {
    $product = Product::factory()->create(['sale_price' => 500, 'discount_price' => 0]);
    $cartKey = $product->id.'-0';
    session(['cart' => [$cartKey => ['product_id' => $product->id, 'quantity' => 1]]]);

    $this->delete(route('cart.remove', $cartKey))
        ->assertRedirect();

    expect(session('cart', []))->toBeEmpty();
});

test('can clear entire cart', function () {
    session(['cart' => ['1-0' => ['product_id' => 1, 'quantity' => 2]]]);

    $this->delete(route('cart.clear'))
        ->assertRedirect();

    expect(session('cart'))->toBeNull();
});
