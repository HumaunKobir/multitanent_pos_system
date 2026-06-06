<?php

use App\Models\Product;
use App\Models\ProductReview;

test('single product page includes reviews and summary', function () {
    $product = Product::factory()->create(['status' => 1]);

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
        );
});

test('guest can submit a product review', function () {
    $product = Product::factory()->create(['status' => 1]);

    $this->post(route('product.reviews.store', $product->slug), [
        'reviewer_name' => 'Jane Doe',
        'rating' => 5,
        'comment' => 'Excellent quality and fast delivery.',
    ])->assertRedirect()
        ->assertSessionHas('success');

    $this->assertDatabaseHas('product_reviews', [
        'product_id' => $product->id,
        'reviewer_name' => 'Jane Doe',
        'rating' => 5,
        'comment' => 'Excellent quality and fast delivery.',
        'status' => 1,
    ]);
});

test('product review submission requires valid data', function () {
    $product = Product::factory()->create(['status' => 1]);

    $this->post(route('product.reviews.store', $product->slug), [])
        ->assertSessionHasErrors(['reviewer_name', 'rating', 'comment']);
});

test('product review submission rejects invalid rating', function () {
    $product = Product::factory()->create(['status' => 1]);

    $this->post(route('product.reviews.store', $product->slug), [
        'reviewer_name' => 'Jane Doe',
        'rating' => 6,
        'comment' => 'Too good.',
    ])->assertSessionHasErrors(['rating']);
});
