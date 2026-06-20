<?php

use App\Enums\OrderStatus;
use App\Models\Customer;
use App\Models\OnlineOrder;
use App\Models\OnlineOrderProduct;
use App\Models\Product;
use App\Models\ProductReview;

function createDeliveredOrderWithProduct(Customer $customer, Product $product, array $orderOverrides = []): OnlineOrder
{
    $order = OnlineOrder::create(array_merge([
        'customer_id' => $customer->id,
        'name' => $customer->name,
        'email' => $customer->email,
        'phone' => $customer->phone,
        'address' => $customer->address ?? 'Dhaka',
        'payment_method' => 'cod',
        'delivery_charge' => 60,
        'subtotal' => 1000,
        'total' => 1060,
        'payment_status' => 'Paid',
        'status' => OrderStatus::Delivered,
    ], $orderOverrides));

    OnlineOrderProduct::create([
        'online_order_id' => $order->id,
        'product_id' => $product->id,
        'name' => $product->name,
        'price' => 1000,
        'quantity' => 1,
        'total_price' => 1000,
    ]);

    return $order;
}

function validReviewPayload(): array
{
    return [
        'reviewer_name' => 'Jane Doe',
        'rating' => 5,
        'comment' => 'Excellent quality and fast delivery.',
    ];
}

test('single product page includes reviews and summary', function () {
    $product = storefrontProduct(['status' => 1]);

    ProductReview::factory()->create([
        'product_id' => $product->id,
        'rating' => 4,
        'status' => 1,
    ]);

    ProductReview::factory()->create([
        'product_id' => $product->id,
        'rating' => 5,
        'status' => 1,
    ]);

    ProductReview::factory()->pending()->create([
        'product_id' => $product->id,
        'rating' => 1,
    ]);

    $this->get(route('product.show', $product->slug))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('frontend/single-product')
            ->has('reviews', 2)
            ->where('reviewSummary.count', 2)
            ->where('reviewSummary.average', 4.5)
            ->where('canReview', false)
        );
});

test('single product page allows review when customer received product', function () {
    $customer = Customer::factory()->create();
    $product = storefrontProduct(['status' => 1]);

    createDeliveredOrderWithProduct($customer, $product);

    $this->actingAs($customer, 'customer')
        ->get(route('product.show', $product->slug))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('canReview', true)
        );
});

test('guest cannot submit a product review', function () {
    $product = storefrontProduct(['status' => 1]);

    $this->post(route('product.reviews.store', $product->slug), validReviewPayload())
        ->assertRedirect()
        ->assertSessionHas('error', 'Please log in to leave a review.');

    $this->assertDatabaseMissing('product_reviews', [
        'product_id' => $product->id,
        'reviewer_name' => 'Jane Doe',
    ]);
});

test('logged in customer cannot review without a delivered order', function () {
    $customer = Customer::factory()->create();
    $product = storefrontProduct(['status' => 1]);

    $this->actingAs($customer, 'customer')
        ->post(route('product.reviews.store', $product->slug), validReviewPayload())
        ->assertSessionHasErrors(['review']);

    $this->assertDatabaseMissing('product_reviews', [
        'product_id' => $product->id,
        'reviewer_name' => 'Jane Doe',
    ]);
});

test('logged in customer cannot review when order is not delivered', function () {
    $customer = Customer::factory()->create();
    $product = storefrontProduct(['status' => 1]);

    createDeliveredOrderWithProduct($customer, $product, ['status' => OrderStatus::Shipping]);

    $this->actingAs($customer, 'customer')
        ->post(route('product.reviews.store', $product->slug), validReviewPayload())
        ->assertSessionHasErrors(['review']);
});

test('customer can submit review after product is delivered', function () {
    $customer = Customer::factory()->create();
    $product = storefrontProduct(['status' => 1]);

    createDeliveredOrderWithProduct($customer, $product);

    $this->actingAs($customer, 'customer')
        ->post(route('product.reviews.store', $product->slug), validReviewPayload())
        ->assertRedirect()
        ->assertSessionHas('success');

    $this->assertDatabaseHas('product_reviews', [
        'product_id' => $product->id,
        'reviewer_name' => 'Jane Doe',
        'rating' => 5,
        'comment' => 'Excellent quality and fast delivery.',
        'status' => 1,
    ]);
});

test('customer can review product from any delivered order containing it', function () {
    $customer = Customer::factory()->create();
    $product = storefrontProduct(['status' => 1]);
    $otherProduct = storefrontProduct(['status' => 1]);

    $order = createDeliveredOrderWithProduct($customer, $otherProduct);

    OnlineOrderProduct::create([
        'online_order_id' => $order->id,
        'product_id' => $product->id,
        'name' => $product->name,
        'price' => 500,
        'quantity' => 1,
        'total_price' => 500,
    ]);

    $this->actingAs($customer, 'customer')
        ->post(route('product.reviews.store', $product->slug), validReviewPayload())
        ->assertRedirect()
        ->assertSessionHas('success');

    $this->assertDatabaseHas('product_reviews', [
        'product_id' => $product->id,
        'reviewer_name' => 'Jane Doe',
    ]);
});

test('customer cannot review product purchased by another customer', function () {
    $customer = Customer::factory()->create();
    $otherCustomer = Customer::factory()->create();
    $product = storefrontProduct(['status' => 1]);

    createDeliveredOrderWithProduct($otherCustomer, $product);

    $this->actingAs($customer, 'customer')
        ->post(route('product.reviews.store', $product->slug), validReviewPayload())
        ->assertSessionHasErrors(['review']);
});

test('product review submission requires valid data', function () {
    $product = storefrontProduct(['status' => 1]);

    $this->post(route('product.reviews.store', $product->slug), [])
        ->assertSessionHasErrors(['reviewer_name', 'rating', 'comment']);
});

test('product review submission rejects invalid rating', function () {
    $product = storefrontProduct(['status' => 1]);

    $this->post(route('product.reviews.store', $product->slug), [
        'reviewer_name' => 'Jane Doe',
        'rating' => 6,
        'comment' => 'Too good.',
    ])->assertSessionHasErrors(['rating']);
});
