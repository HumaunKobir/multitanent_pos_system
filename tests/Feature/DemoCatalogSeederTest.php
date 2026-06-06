<?php

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariation;
use App\Models\Tag;
use Database\Seeders\DemoCatalogSeeder;

test('demo catalog seeder creates categories brands tags and products with variants', function () {
    $this->seed(DemoCatalogSeeder::class);

    expect(Category::query()->count())->toBeGreaterThanOrEqual(5)
        ->and(Brand::query()->count())->toBeGreaterThanOrEqual(5)
        ->and(Tag::query()->count())->toBeGreaterThanOrEqual(16)
        ->and(Product::query()->count())->toBe(8);

    $variantProduct = Product::query()
        ->where('slug', 'premium-cotton-panjabi')
        ->with('variations')
        ->first();

    expect($variantProduct)->not->toBeNull()
        ->and($variantProduct->category)->not->toBeNull()
        ->and($variantProduct->brand)->not->toBeNull()
        ->and($variantProduct->tags)->toContain('Eid')
        ->and($variantProduct->variations)->toHaveCount(6)
        ->and($variantProduct->variations->first()->variation_data)->toHaveKeys(['Size', 'Color', 'label']);

    $simpleProduct = Product::query()
        ->where('slug', 'essential-cotton-t-shirt')
        ->withCount('variations')
        ->first();

    expect($simpleProduct)->not->toBeNull()
        ->and($simpleProduct->sale_price)->toBe('690.00')
        ->and($simpleProduct->variations_count)->toBe(0);

    expect(ProductVariation::query()->count())->toBeGreaterThanOrEqual(25);
});

test('demo catalog seeder is idempotent', function () {
    $this->seed(DemoCatalogSeeder::class);
    $productCount = Product::query()->count();
    $variationCount = ProductVariation::query()->count();

    $this->seed(DemoCatalogSeeder::class);

    expect(Product::query()->count())->toBe($productCount)
        ->and(ProductVariation::query()->count())->toBe($variationCount);
});

test('seeded variant product appears on category page with variation summary', function () {
    $this->seed(DemoCatalogSeeder::class);

    $category = Category::query()->where('slug', 'panjabi')->firstOrFail();

    $this->get(route('category.products', $category->slug))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('frontend/category-products')
            ->where('category.slug', 'panjabi')
            ->has('products.data', 2)
            ->where('products.data', fn ($products) => collect($products)->contains(
                fn ($product) => $product['slug'] === 'premium-cotton-panjabi'
                    && $product['has_variations'] === true
                    && $product['variations_count'] === 6,
            ))
        );
});
