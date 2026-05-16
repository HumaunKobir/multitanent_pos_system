<?php

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductSection;

test('home page loads successfully', function () {
    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('frontend/home'));
});

test('home page contains product sections', function () {
    $product = Product::factory()->create();

    ProductSection::create([
        'name' => 'Test Section',
        'description' => 'desc',
        'button_text' => 'View',
        'block_per_line' => 4,
        'layout_type' => 1,
        'block_type' => 2,
        'items' => [$product->id],
        'status' => 1,
        'serial' => 1,
    ]);

    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('frontend/home')
            ->has('productSections', 1)
        );
});

test('single product page shows product by slug', function () {
    $product = Product::factory()->create(['status' => 1]);

    $this->get(route('product.show', $product->slug))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('frontend/single-product')
            ->where('product.slug', $product->slug)
        );
});

test('single product page returns 404 for invalid slug', function () {
    $this->get(route('product.show', 'non-existent-slug'))
        ->assertNotFound();
});

test('single product page returns 404 for inactive product', function () {
    $product = Product::factory()->create(['status' => 0]);

    $this->get(route('product.show', $product->slug))
        ->assertNotFound();
});

test('collection products page renders', function () {
    $this->get(route('collection.products', 'Summer'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('frontend/collection-products')
            ->where('collectionName', 'Summer')
        );
});

test('category products page renders for valid category', function () {
    $category = Category::factory()->create();

    $this->get(route('category.products', $category->id))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('frontend/category-products'));
});

test('category products page returns 404 for invalid category', function () {
    $this->get(route('category.products', 9999))
        ->assertNotFound();
});

test('search page returns matching products', function () {
    Product::factory()->create(['name' => 'Demo Shirt', 'status' => 1, 'visible' => 'yes']);

    $this->get(route('search', ['q' => 'Demo']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('frontend/search')
            ->where('query', 'Demo')
        );
});

test('contact page loads', function () {
    $this->get(route('contact'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('frontend/contact'));
});

test('contact form stores message and redirects', function () {
    $this->post(route('contact.store'), [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'phone' => '01700000000',
        'subject' => 'Test Subject',
        'message' => 'Test message body',
    ])->assertRedirect();

    $this->assertDatabaseHas('contacts', ['email' => 'test@example.com']);
});

test('contact form requires name and message', function () {
    $this->post(route('contact.store'), [])
        ->assertSessionHasErrors(['name', 'message']);
});

test('about static page loads', function () {
    $this->get(route('about'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('frontend/static-page')
            ->where('page', 'about')
        );
});

test('faq static page loads', function () {
    $this->get(route('faq'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('frontend/static-page'));
});
