<?php

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;

function productIndexAdmin(): User
{
    return User::factory()->create(['branch_id' => null]);
}

test('product index page passes brands and tags props', function () {
    $admin = productIndexAdmin();

    $this->actingAs($admin)
        ->get(route('product.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/product/index')
            ->has('brands')
            ->has('tags')
            ->has('products.data')
            ->has('products.links')
            ->where('products.per_page', 10));
});

test('product index can filter by brand', function () {
    $admin = productIndexAdmin();
    $brand1 = Brand::factory()->create(['status' => 1]);
    $brand2 = Brand::factory()->create(['status' => 1]);
    $category = Category::factory()->create(['status' => 1]);

    Product::factory()->create(['category_id' => $category->id, 'brand_id' => $brand1->id, 'name' => 'Product A', 'status' => 1]);
    Product::factory()->create(['category_id' => $category->id, 'brand_id' => $brand2->id, 'name' => 'Product B', 'status' => 1]);

    $response = $this->actingAs($admin)
        ->get(route('product.index', ['brand_id' => $brand1->id]))
        ->assertOk();

    $response->assertInertia(fn ($page) => $page
        ->component('admin/product/index')
        ->where('filters.brand_id', (string) $brand1->id)
        ->has('products', fn ($page) => $page
            ->where('data', fn ($data) => collect($data)->every(fn ($p) => $p['brand_id'] === $brand1->id))
        ));
});

test('product index can filter by tag', function () {
    $admin = productIndexAdmin();
    $category = Category::factory()->create(['status' => 1]);

    Product::factory()->create(['category_id' => $category->id, 'tags' => ['Casual'], 'name' => 'Casual Shirt', 'status' => 1]);
    Product::factory()->create(['category_id' => $category->id, 'tags' => ['Formal'], 'name' => 'Formal Shirt', 'status' => 1]);

    $response = $this->actingAs($admin)
        ->get(route('product.index', ['tag' => 'Casual']))
        ->assertOk();

    $response->assertInertia(fn ($page) => $page
        ->component('admin/product/index')
        ->where('filters.tag', 'Casual'));
});

test('product index search includes brand name', function () {
    $admin = productIndexAdmin();
    $brand = Brand::factory()->create(['name' => 'Nike Special', 'status' => 1]);
    $category = Category::factory()->create(['status' => 1]);

    Product::factory()->create(['category_id' => $category->id, 'brand_id' => $brand->id, 'name' => 'Running Shoe', 'status' => 1]);

    $response = $this->actingAs($admin)
        ->get(route('product.index', ['search' => 'Nike Special']))
        ->assertOk();

    $response->assertInertia(fn ($page) => $page
        ->component('admin/product/index')
        ->has('products', fn ($page) => $page
            ->where('data', fn ($data) => count($data) >= 1)
        ));
});

test('product index search includes category name', function () {
    $admin = productIndexAdmin();
    $category = Category::factory()->create(['name' => 'Electronics Pro', 'status' => 1]);

    Product::factory()->create(['category_id' => $category->id, 'name' => 'Widget Device', 'status' => 1]);

    $response = $this->actingAs($admin)
        ->get(route('product.index', ['search' => 'Electronics Pro']))
        ->assertOk();

    $response->assertInertia(fn ($page) => $page
        ->component('admin/product/index')
        ->has('products', fn ($page) => $page
            ->where('data', fn ($data) => count($data) >= 1)
        ));
});

test('product index search includes tag name', function () {
    $admin = productIndexAdmin();
    $category = Category::factory()->create(['status' => 1]);

    Product::factory()->create(['category_id' => $category->id, 'tags' => ['Summer Vibes'], 'name' => 'Beach Towel', 'status' => 1]);

    $response = $this->actingAs($admin)
        ->get(route('product.index', ['search' => 'Summer Vibes']))
        ->assertOk();

    $response->assertInertia(fn ($page) => $page
        ->component('admin/product/index')
        ->has('products', fn ($page) => $page
            ->where('data', fn ($data) => count($data) >= 1)
        ));
});

test('product index returns empty filters when no filters provided', function () {
    $admin = productIndexAdmin();

    $this->actingAs($admin)
        ->get(route('product.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/product/index')
            ->where('filters.search', null)
            ->where('filters.category_id', null)
            ->where('filters.brand_id', null)
            ->where('filters.tag', null));
});
