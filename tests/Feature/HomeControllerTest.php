<?php

use App\Enums\CommonStatus;
use App\Models\Branch;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductReview;
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
    $name = 'Test Section '.fake()->unique()->numerify('####');

    ProductSection::create([
        'name' => $name,
        'description' => 'desc',
        'button_text' => 'View',
        'block_per_line' => 4,
        'layout_type' => 1,
        'block_type' => 2,
        'items' => [$product->id],
        'status' => 1,
        'serial' => 9998,
    ]);

    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('frontend/home')
            ->where('productSections', fn ($sections) => collect($sections)->contains('name', $name))
        );
});

test('home page product section includes formatted products in admin order', function () {
    $first = Product::factory()->create([
        'name' => 'Section First '.fake()->unique()->numerify('####'),
        'status' => 1,
        'visible' => 'yes',
    ]);
    $second = Product::factory()->create([
        'name' => 'Section Second '.fake()->unique()->numerify('####'),
        'status' => 1,
        'visible' => 'yes',
    ]);
    $inactive = Product::factory()->create([
        'name' => 'Section Inactive '.fake()->unique()->numerify('####'),
        'status' => 0,
        'visible' => 'yes',
    ]);
    $name = 'Ordered Section '.fake()->unique()->numerify('####');

    ProductSection::create([
        'name' => $name,
        'description' => 'Featured picks',
        'button_text' => 'View All',
        'block_per_line' => 4,
        'layout_type' => 1,
        'block_type' => 2,
        'items' => [$second->id, $first->id, $inactive->id],
        'status' => 1,
        'serial' => 9997,
    ]);

    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('productSections', fn ($sections) => ($section = collect($sections)->firstWhere('name', $name)) !== null
                && $section['layout_type'] === 1
                && $section['block_type'] === 2
                && count($section['products']) === 2
                && $section['products'][0]['slug'] === $second->slug
                && $section['products'][1]['slug'] === $first->slug
            )
        );
});

test('home page product section slider layout is exposed to frontend', function () {
    $product = Product::factory()->create(['status' => 1, 'visible' => 'yes']);
    $name = 'Slider Section '.fake()->unique()->numerify('####');

    ProductSection::create([
        'name' => $name,
        'block_per_line' => 3,
        'layout_type' => 2,
        'block_type' => 2,
        'items' => [$product->id],
        'status' => 1,
        'serial' => 9996,
    ]);

    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('productSections', fn ($sections) => ($section = collect($sections)->firstWhere('name', $name)) !== null
                && $section['layout_type'] === 2
                && $section['products'][0]['slug'] === $product->slug
            )
        );
});

test('home page image section includes banner metadata', function () {
    $name = 'Banner Section '.fake()->unique()->numerify('####');

    ProductSection::create([
        'name' => $name,
        'block_per_line' => 2,
        'layout_type' => 1,
        'block_type' => 1,
        'images' => [
            [
                'image_name' => 'Summer Sale',
                'image' => 'product-sections/summer.jpg',
                'button_text' => 'Shop Now',
                'link' => '/products',
                'description' => 'Up to 50% off',
            ],
        ],
        'status' => 1,
        'serial' => 9995,
    ]);

    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('productSections', fn ($sections) => ($section = collect($sections)->firstWhere('name', $name)) !== null
                && $section['block_type'] === 1
                && $section['images'][0]['image_name'] === 'Summer Sale'
                && $section['images'][0]['button_text'] === 'Shop Now'
                && $section['images'][0]['link'] === '/products'
                && str_contains($section['images'][0]['image'], '/storage/product-sections/summer.jpg')
            )
        );
});

test('home page excludes inactive product sections', function () {
    $name = 'Hidden Section '.fake()->unique()->numerify('####');

    ProductSection::create([
        'name' => $name,
        'block_per_line' => 4,
        'layout_type' => 1,
        'block_type' => 2,
        'items' => [],
        'status' => 0,
        'serial' => 9999,
    ]);

    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('productSections', fn ($sections) => ! collect($sections)->contains('name', $name))
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

test('all products page renders visible products', function () {
    $visibleProduct = Product::factory()->create([
        'name' => 'All Products Visible '.fake()->unique()->numerify('####'),
        'status' => 1,
        'visible' => 'yes',
    ]);

    Product::factory()->create([
        'name' => 'All Products Hidden '.fake()->unique()->numerify('####'),
        'status' => 0,
        'visible' => 'yes',
    ]);

    $this->get(route('products.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('frontend/all-products')
            ->where('products.data', fn ($products) => collect($products)->contains('slug', $visibleProduct->slug))
        );
});

test('product listings include approved review summary', function () {
    $product = Product::factory()->create([
        'status' => 1,
        'visible' => 'yes',
    ]);

    ProductReview::factory()->create([
        'product_id' => $product->id,
        'rating' => 5,
        'status' => 1,
    ]);

    ProductReview::factory()->create([
        'product_id' => $product->id,
        'rating' => 4,
        'status' => 1,
    ]);

    ProductReview::factory()->pending()->create([
        'product_id' => $product->id,
        'rating' => 1,
    ]);

    $this->get(route('products.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('frontend/all-products')
            ->where('products.data', fn ($products) => collect($products)
                ->firstWhere('slug', $product->slug)['review_summary']['count'] === 2
                && collect($products)->firstWhere('slug', $product->slug)['review_summary']['average'] === 4.5
            )
        );
});

test('single product page still resolves when all products route exists', function () {
    $product = Product::factory()->create(['status' => 1, 'visible' => 'yes']);

    $this->get(route('product.show', $product->slug))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('frontend/single-product')
            ->where('product.slug', $product->slug)
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
    $term = 'Unique Search Term '.fake()->unique()->numerify('####');
    Product::factory()->create(['name' => $term.' Shirt', 'status' => 1, 'visible' => 'yes']);

    $this->get(route('search', ['q' => $term]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('frontend/search')
            ->where('query', $term)
            ->has('products.data', 1)
        );
});

test('search page matches products by brand name', function () {
    $brand = Brand::factory()->create(['name' => 'Search Brand '.fake()->unique()->numerify('####'), 'status' => 1]);
    Product::factory()->create([
        'name' => 'Hidden Label Product',
        'brand_id' => $brand->id,
        'status' => 1,
        'visible' => 'yes',
    ]);

    $this->get(route('search', ['q' => $brand->name]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('frontend/search')
            ->where('query', $brand->name)
            ->has('products.data', 1)
        );
});

test('search page preserves query string in pagination links', function () {
    $term = 'Paged Search Term '.fake()->unique()->numerify('####');

    Product::factory()->count(13)->create([
        'name' => $term.' Item',
        'status' => 1,
        'visible' => 'yes',
    ]);

    $this->get(route('search', ['q' => $term]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('frontend/search')
            ->where('query', $term)
            ->where('products.total', 13)
            ->where('products.last_page', 2)
            ->where('products.next_page_url', fn (?string $url): bool => is_string($url) && str_contains($url, 'q='))
        );
});

test('search suggestions returns matching products with images', function () {
    $term = 'Suggestion Term '.fake()->unique()->numerify('####');
    $product = Product::factory()->create([
        'name' => $term.' Jacket',
        'image' => 'products/test-image.jpg',
        'status' => 1,
        'visible' => 'yes',
    ]);

    $this->getJson(route('search.suggestions', ['q' => $term]))
        ->assertOk()
        ->assertJsonCount(1)
        ->assertJsonFragment([
            'id' => $product->id,
            'name' => $product->name,
            'slug' => $product->slug,
        ])
        ->assertJsonPath('0.image', fn (?string $image): bool => is_string($image) && $image !== '');
});

test('search suggestions returns empty array for short queries', function () {
    $this->getJson(route('search.suggestions', ['q' => 'a']))
        ->assertOk()
        ->assertExactJson([]);
});

test('search page returns no products when query is empty', function () {
    Product::factory()->create(['name' => 'Visible Product', 'status' => 1, 'visible' => 'yes']);

    $this->get(route('search'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('frontend/search')
            ->where('query', '')
            ->where('products.total', 0)
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
