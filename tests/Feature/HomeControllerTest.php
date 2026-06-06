<?php

use App\Enums\CommonStatus;
use App\Models\Branch;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductSection;
use App\Models\ProductVariation;
use App\Models\Tag;
use App\Services\EcommerceBranchService;

test('home page loads successfully', function () {
    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('frontend/home'));
});

test('home page shares storefront navigation filters', function () {
    $category = Category::factory()->create(['status' => 1]);
    $brand = Brand::factory()->create(['status' => 1]);
    $tag = Tag::create([
        'name' => 'Nav Tag '.fake()->unique()->numerify('####'),
        'status' => CommonStatus::Active,
        'branch_id' => null,
    ]);

    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('categories')
            ->has('brands')
            ->has('tags')
            ->where('categories', fn ($categories) => collect($categories)->contains('slug', $category->slug))
            ->where('brands', fn ($brands) => collect($brands)->contains('slug', $brand->slug))
            ->where('tags', fn ($tags) => collect($tags)->contains('name', $tag->name))
        );
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
    $product = Product::factory()->create([
        'status' => 1,
        'image' => 'products/sample.jpg',
    ]);

    $this->get(route('product.show', $product->slug))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('frontend/single-product')
            ->where('product.slug', $product->slug)
            ->where('product.image', fn ($image) => str_contains($image, '/storage/products/sample.jpg'))
            ->has('reviews')
            ->has('reviewSummary')
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

test('category products page renders for valid category slug', function () {
    $category = Category::factory()->create(['name' => 'Summer Wear']);

    $this->get(route('category.products', $category->slug))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('frontend/category-products')
            ->where('category.slug', $category->slug)
        );
});

test('category products include variation summary for variant products', function () {
    $category = Category::factory()->create(['name' => 'Variant Wear']);
    $product = Product::factory()->create([
        'category_id' => $category->id,
        'status' => 1,
        'visible' => 'yes',
        'sale_price' => 0,
    ]);

    ProductVariation::query()->create([
        'product_id' => $product->id,
        'sku' => 'VAR-M',
        'variation_data' => ['Size' => 'M'],
        'price' => 1200,
        'stock' => 5,
        'status' => CommonStatus::Active,
    ]);

    ProductVariation::query()->create([
        'product_id' => $product->id,
        'sku' => 'VAR-L',
        'variation_data' => ['Size' => 'L'],
        'price' => 1350,
        'stock' => 3,
        'status' => CommonStatus::Active,
    ]);

    $this->get(route('category.products', $category->slug))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('frontend/category-products')
            ->where('products.data.0.has_variations', true)
            ->where('products.data.0.variations_count', 2)
            ->where('products.data.0.price_min', 1200)
            ->where('products.data.0.price_max', 1350)
            ->has('products.data.0.variations', 2)
        );
});

test('category products page redirects legacy id urls to slug', function () {
    $category = Category::factory()->create(['name' => 'Winter Wear']);

    $this->get("/category/{$category->id}/products")
        ->assertRedirect(route('category.products', $category->slug));
});

test('category products page returns 404 for invalid category slug', function () {
    $this->get(route('category.products', 'non-existent-category'))
        ->assertNotFound();
});

test('brand products page renders for valid brand slug', function () {
    $brand = Brand::factory()->create(['name' => 'Cool Brand']);

    $this->get(route('brand.products', $brand->slug))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('frontend/brand-products')
            ->where('brand.slug', $brand->slug)
        );
});

test('brand products page redirects legacy id urls to slug', function () {
    $brand = Brand::factory()->create(['name' => 'Legacy Brand']);

    $this->get("/brand/{$brand->id}/products")
        ->assertRedirect(route('brand.products', $brand->slug));
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

test('contact form stores message for ecommerce branch and redirects', function () {
    $ecommerceBranch = Branch::query()->firstOrCreate(
        ['name' => EcommerceBranchService::BRANCH_NAME],
        Branch::factory()->make(['name' => EcommerceBranchService::BRANCH_NAME])->toArray(),
    );

    EcommerceBranchService::resetResolvedId();

    $this->post(route('contact.store'), [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'phone' => '01700000000',
        'message' => 'Test message body',
    ])->assertRedirect();

    $this->assertDatabaseHas('contacts', [
        'email' => 'test@example.com',
        'message' => 'Test message body',
        'branch_id' => $ecommerceBranch->id,
    ]);
});

test('contact form requires name and message', function () {
    $this->post(route('contact.store'), [])
        ->assertSessionHasErrors(['name', 'message']);
});

test('about page loads', function () {
    $this->get(route('about'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('frontend/about')
            ->has('content')
            ->has('heroImage')
        );
});

test('faq static page loads', function () {
    $this->get(route('faq'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('frontend/static-page'));
});
