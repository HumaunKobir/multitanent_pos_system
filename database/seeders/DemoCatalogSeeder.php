<?php

namespace Database\Seeders;

use App\Enums\CommonStatus;
use App\Models\Barcode;
use App\Models\Branch;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariation;
use App\Models\Tag;
use App\Models\Unit;
use App\Models\Variation;
use App\Models\VariationValue;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DemoCatalogSeeder extends Seeder
{
    /** @var array<string, int> */
    private array $categories = [];

    /** @var array<string, int> */
    private array $brands = [];

    /** @var array<string, int> */
    private array $units = [];

    /** @var array<string, int> */
    private array $tags = [];

    /** @var array<string, array{variation_id: int, values: array<string, int>}> */
    private array $variationAxes = [];

    private ?int $branchId = null;

    public function run(): void
    {
        $this->branchId = Branch::query()
            ->where('name', Branch::ECOMMERCE_BRANCH_NAME)
            ->value('id');

        $this->seedCategories();
        $this->seedBrands();
        $this->seedTags();
        $this->seedUnits();
        $this->seedVariationAxes();
        $this->seedProducts();
    }

    private function seedCategories(): void
    {
        foreach ([
            'Panjabi' => 'Traditional and modern panjabi for every occasion.',
            'T-Shirt' => 'Everyday cotton and graphic tees.',
            'Hoodie' => 'Comfortable fleece and cotton hoodies.',
            'Formal Shirt' => 'Office-ready shirts with a sharp fit.',
            'Pant' => 'Chinos, trousers, and casual pants.',
        ] as $name => $description) {
            $category = Category::query()->updateOrCreate(
                ['slug' => Str::slug($name)],
                ['name' => $name, 'status' => 1],
            );

            $this->categories[$name] = $category->id;
        }
    }

    private function seedBrands(): void
    {
        foreach ([
            'Studio',
            'Heritage Loom',
            'Urban Edge',
            'Classic Fit',
            'Nova Thread',
        ] as $name) {
            $brand = Brand::query()->updateOrCreate(
                ['slug' => Str::slug($name)],
                ['name' => $name, 'status' => 1],
            );

            $this->brands[$name] = $brand->id;
        }
    }

    private function seedTags(): void
    {
        $groups = [
            'Occasion' => ['Eid', 'Wedding', 'Casual', 'Office'],
            'Style' => ['Premium', 'Trending', 'Best Seller', 'New Arrival'],
            'Season' => ['Summer', 'Winter', 'Monsoon'],
            'Collection' => ['Ramadan Edit', 'Festive Drop', 'Daily Wear'],
        ];

        foreach ($groups as $parentName => $children) {
            $parent = Tag::query()->updateOrCreate(
                [
                    'branch_id' => $this->branchId,
                    'name' => $parentName,
                    'parent_id' => null,
                ],
                ['status' => CommonStatus::Active],
            );

            $this->tags[$parentName] = $parent->id;

            foreach ($children as $childName) {
                $child = Tag::query()->updateOrCreate(
                    [
                        'branch_id' => $this->branchId,
                        'name' => $childName,
                        'parent_id' => $parent->id,
                    ],
                    ['status' => CommonStatus::Active],
                );

                $this->tags[$childName] = $child->id;
            }
        }
    }

    private function seedUnits(): void
    {
        foreach (['Piece', 'Set'] as $name) {
            $unit = Unit::query()->updateOrCreate(
                ['name' => $name],
                ['status' => 1],
            );

            $this->units[$name] = $unit->id;
        }
    }

    private function seedVariationAxes(): void
    {
        $axes = [
            'Size' => ['S', 'M', 'L', 'XL', 'XXL'],
            'Color' => ['White', 'Black', 'Navy', 'Maroon', 'Sky Blue', 'Olive'],
        ];

        foreach ($axes as $name => $values) {
            $variation = Variation::query()->updateOrCreate(
                [
                    'branch_id' => $this->branchId,
                    'name' => $name,
                ],
                ['status' => CommonStatus::Active],
            );

            $valueMap = [];

            foreach ($values as $value) {
                $variationValue = VariationValue::query()->updateOrCreate(
                    [
                        'variation_id' => $variation->id,
                        'value' => $value,
                    ],
                    ['status' => CommonStatus::Active],
                );

                $valueMap[$value] = $variationValue->id;
            }

            $this->variationAxes[$name] = [
                'variation_id' => $variation->id,
                'values' => $valueMap,
            ];
        }
    }

    private function seedProducts(): void
    {
        $catalog = [
            [
                'name' => 'Premium Cotton Panjabi',
                'slug' => 'premium-cotton-panjabi',
                'category' => 'Panjabi',
                'brand' => 'Heritage Loom',
                'unit' => 'Piece',
                'tags' => ['Eid', 'Premium', 'Best Seller', 'Festive Drop'],
                'image' => 'https://picsum.photos/seed/cp-panjabi/600/800',
                'description' => '<p>Soft premium cotton panjabi with minimal embroidery. Breathable fabric ideal for Eid and festive gatherings.</p><ul><li>100% combed cotton</li><li>Regular fit</li><li>Machine wash cold</li></ul>',
                'delivery_info' => 'Inside Dhaka: 1–2 days · Outside Dhaka: 2–4 days · SSLCommerz or cash on delivery.',
                'combinations' => [
                    ['Size' => 'M', 'Color' => 'White', 'price' => 2490, 'purchase_price' => 1650, 'stock' => 12],
                    ['Size' => 'L', 'Color' => 'White', 'price' => 2490, 'purchase_price' => 1650, 'stock' => 15],
                    ['Size' => 'XL', 'Color' => 'White', 'price' => 2590, 'purchase_price' => 1700, 'stock' => 10],
                    ['Size' => 'M', 'Color' => 'Sky Blue', 'price' => 2490, 'purchase_price' => 1650, 'stock' => 8],
                    ['Size' => 'L', 'Color' => 'Sky Blue', 'price' => 2490, 'purchase_price' => 1650, 'stock' => 9],
                    ['Size' => 'XL', 'Color' => 'Maroon', 'price' => 2590, 'purchase_price' => 1700, 'stock' => 6],
                ],
            ],
            [
                'name' => 'Embroidered Festive Panjabi',
                'slug' => 'embroidered-festive-panjabi',
                'category' => 'Panjabi',
                'brand' => 'Coolness Studio',
                'unit' => 'Piece',
                'tags' => ['Wedding', 'Premium', 'Ramadan Edit', 'New Arrival'],
                'image' => 'https://picsum.photos/seed/cp-festive-panjabi/600/800',
                'description' => '<p>Hand-finished collar embroidery with a structured festive silhouette. Perfect for wedding guests and special occasions.</p>',
                'delivery_info' => 'Express delivery available inside Dhaka for orders placed before 3 PM.',
                'combinations' => [
                    ['Size' => 'M', 'Color' => 'Maroon', 'price' => 3890, 'purchase_price' => 2600, 'stock' => 5],
                    ['Size' => 'L', 'Color' => 'Maroon', 'price' => 3890, 'purchase_price' => 2600, 'stock' => 7],
                    ['Size' => 'XL', 'Color' => 'Navy', 'price' => 3990, 'purchase_price' => 2650, 'stock' => 4],
                    ['Size' => 'XXL', 'Color' => 'Navy', 'price' => 4090, 'purchase_price' => 2700, 'stock' => 3],
                ],
            ],
            [
                'name' => 'Classic Fit Formal Shirt',
                'slug' => 'classic-fit-formal-shirt',
                'category' => 'Formal Shirt',
                'brand' => 'Classic Fit',
                'unit' => 'Piece',
                'tags' => ['Office', 'Daily Wear', 'Best Seller'],
                'image' => 'https://picsum.photos/seed/cp-formal-shirt/600/800',
                'description' => '<p>Wrinkle-resistant formal shirt with a clean classic fit. Ideal for office and business casual looks.</p>',
                'delivery_info' => 'Free return within 7 days if size does not fit.',
                'combinations' => [
                    ['Size' => 'S', 'Color' => 'White', 'price' => 1490, 'purchase_price' => 950, 'stock' => 14],
                    ['Size' => 'M', 'Color' => 'White', 'price' => 1490, 'purchase_price' => 950, 'stock' => 20],
                    ['Size' => 'L', 'Color' => 'White', 'price' => 1490, 'purchase_price' => 950, 'stock' => 18],
                    ['Size' => 'M', 'Color' => 'Sky Blue', 'price' => 1590, 'purchase_price' => 1020, 'stock' => 11],
                    ['Size' => 'L', 'Color' => 'Sky Blue', 'price' => 1590, 'purchase_price' => 1020, 'stock' => 9],
                ],
            ],
            [
                'name' => 'Urban Graphic Tee',
                'slug' => 'urban-graphic-tee',
                'category' => 'T-Shirt',
                'brand' => 'Urban Edge',
                'unit' => 'Piece',
                'tags' => ['Casual', 'Trending', 'Summer', 'Daily Wear'],
                'image' => 'https://picsum.photos/seed/cp-graphic-tee/600/800',
                'description' => '<p>180 GSM cotton tee with a soft hand-feel print. Relaxed fit for everyday street style.</p>',
                'delivery_info' => 'Standard delivery ৳60 inside Dhaka.',
                'combinations' => [
                    ['Size' => 'S', 'Color' => 'Black', 'price' => 890, 'purchase_price' => 520, 'stock' => 16],
                    ['Size' => 'M', 'Color' => 'Black', 'price' => 890, 'purchase_price' => 520, 'stock' => 22],
                    ['Size' => 'L', 'Color' => 'Black', 'price' => 890, 'purchase_price' => 520, 'stock' => 19],
                    ['Size' => 'M', 'Color' => 'Olive', 'price' => 890, 'purchase_price' => 520, 'stock' => 13],
                    ['Size' => 'L', 'Color' => 'Olive', 'price' => 890, 'purchase_price' => 520, 'stock' => 10],
                ],
            ],
            [
                'name' => 'Fleece Pullover Hoodie',
                'slug' => 'fleece-pullover-hoodie',
                'category' => 'Hoodie',
                'brand' => 'Nova Thread',
                'unit' => 'Piece',
                'tags' => ['Casual', 'Winter', 'Trending', 'New Arrival'],
                'image' => 'https://picsum.photos/seed/cp-hoodie/600/800',
                'description' => '<p>Mid-weight fleece hoodie with kangaroo pocket and ribbed cuffs. Built for cool evenings and travel days.</p>',
                'delivery_info' => 'Ships within 24 hours from Dhaka warehouse.',
                'combinations' => [
                    ['Size' => 'M', 'Color' => 'Black', 'price' => 1890, 'purchase_price' => 1180, 'stock' => 12],
                    ['Size' => 'L', 'Color' => 'Black', 'price' => 1890, 'purchase_price' => 1180, 'stock' => 15],
                    ['Size' => 'XL', 'Color' => 'Black', 'price' => 1990, 'purchase_price' => 1240, 'stock' => 8],
                    ['Size' => 'L', 'Color' => 'Navy', 'price' => 1890, 'purchase_price' => 1180, 'stock' => 7],
                    ['Size' => 'XL', 'Color' => 'Navy', 'price' => 1990, 'purchase_price' => 1240, 'stock' => 6],
                ],
            ],
            [
                'name' => 'Slim Stretch Chino Pant',
                'slug' => 'slim-stretch-chino-pant',
                'category' => 'Pant',
                'brand' => 'Classic Fit',
                'unit' => 'Piece',
                'tags' => ['Office', 'Daily Wear', 'Best Seller'],
                'image' => 'https://picsum.photos/seed/cp-chino/600/800',
                'description' => '<p>Slim-fit chino with 2% stretch for all-day comfort. Tapered leg with clean minimal styling.</p>',
                'delivery_info' => 'Exchange available for size issues within 5 days.',
                'combinations' => [
                    ['Size' => '30', 'Color' => 'Olive', 'price' => 1790, 'purchase_price' => 1100, 'stock' => 9],
                    ['Size' => '32', 'Color' => 'Olive', 'price' => 1790, 'purchase_price' => 1100, 'stock' => 14],
                    ['Size' => '34', 'Color' => 'Olive', 'price' => 1790, 'purchase_price' => 1100, 'stock' => 11],
                    ['Size' => '32', 'Color' => 'Navy', 'price' => 1790, 'purchase_price' => 1100, 'stock' => 10],
                    ['Size' => '34', 'Color' => 'Navy', 'price' => 1790, 'purchase_price' => 1100, 'stock' => 8],
                ],
            ],
            [
                'name' => 'Essential Cotton T-Shirt',
                'slug' => 'essential-cotton-t-shirt',
                'category' => 'T-Shirt',
                'brand' => 'Coolness Studio',
                'unit' => 'Piece',
                'tags' => ['Casual', 'Daily Wear', 'Summer'],
                'image' => 'https://picsum.photos/seed/cp-essential-tee/600/800',
                'description' => '<p>Single-jersey cotton tee with a regular fit. A wardrobe basic that pairs with everything.</p>',
                'delivery_info' => 'Buy 3 and get flat ৳100 off at checkout.',
                'sale_price' => 690,
                'purchase_price' => 420,
                'discount_price' => 590,
                'code' => 'CP-TEE-001',
            ],
            [
                'name' => 'Linen Blend Casual Shirt',
                'slug' => 'linen-blend-casual-shirt',
                'category' => 'Formal Shirt',
                'brand' => 'Heritage Loom',
                'unit' => 'Piece',
                'tags' => ['Casual', 'Summer', 'Monsoon', 'New Arrival'],
                'image' => 'https://picsum.photos/seed/cp-linen-shirt/600/800',
                'description' => '<p>Lightweight linen blend shirt with a relaxed drape. Keeps you cool during humid Bangladesh summers.</p>',
                'delivery_info' => 'Iron on low heat. Do not bleach.',
                'sale_price' => 1690,
                'purchase_price' => 1050,
                'discount_price' => 0,
                'code' => 'CP-SHT-002',
            ],
        ];

        foreach ($catalog as $item) {
            $this->seedProduct($item);
        }
    }

    /** @param array<string, mixed> $item */
    private function seedProduct(array $item): void
    {
        $hasVariations = ! empty($item['combinations']);

        $product = Product::query()->updateOrCreate(
            ['slug' => $item['slug']],
            [
                'branch_id' => $this->branchId,
                'category_id' => $this->categories[$item['category']],
                'brand_id' => $this->brands[$item['brand']],
                'unit_id' => $this->units[$item['unit']],
                'name' => $item['name'],
                'code' => $hasVariations ? null : ($item['code'] ?? strtoupper(Str::slug($item['slug'], '-'))),
                'purchase_price' => $hasVariations ? 0 : ($item['purchase_price'] ?? 0),
                'sale_price' => $hasVariations ? 0 : ($item['sale_price'] ?? 0),
                'discount_price' => $hasVariations ? 0 : ($item['discount_price'] ?? 0),
                'tags' => $item['tags'],
                'image' => $item['image'],
                'description' => $item['description'] ?? null,
                'delivery_info' => $item['delivery_info'] ?? null,
                'visible' => 'yes',
                'status' => 1,
            ],
        );

        if ($hasVariations) {
            $this->seedVariations($product, $item['combinations']);

            return;
        }

        $this->seedSimpleProductBarcode($product);
    }

    /** @param list<array{Size?: string, Color?: string, price: int|float, purchase_price: int|float, stock: int}> $combinations */
    private function seedVariations(Product $product, array $combinations): void
    {
        foreach ($combinations as $index => $combo) {
            $size = $combo['Size'] ?? null;
            $color = $combo['Color'] ?? null;
            $parts = array_filter([$size, $color]);
            $label = implode('-', $parts);
            $sku = strtoupper(Str::slug($product->slug, '-')).'-'.str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT);

            /** @var array<string, string> $variationData */
            $variationData = ['label' => $label];

            if ($size !== null) {
                $variationData['Size'] = $size;
            }

            if ($color !== null) {
                $variationData['Color'] = $color;
            }

            $variation = ProductVariation::query()->updateOrCreate(
                [
                    'product_id' => $product->id,
                    'sku' => $sku,
                ],
                [
                    'branch_id' => $product->branch_id,
                    'price' => $combo['price'],
                    'purchase_price' => $combo['purchase_price'],
                    'stock' => $combo['stock'],
                    'variation_data' => $variationData,
                    'status' => CommonStatus::Active,
                ],
            );

            Barcode::query()->updateOrCreate(
                [
                    'product_id' => $product->id,
                    'product_variation_id' => $variation->id,
                ],
                [
                    'branch_id' => $product->branch_id,
                    'code' => $sku,
                    'name' => $product->name.' - '.$label,
                ],
            );
        }
    }

    private function seedSimpleProductBarcode(Product $product): void
    {
        if (! $product->code) {
            return;
        }

        Barcode::query()->updateOrCreate(
            [
                'product_id' => $product->id,
                'product_variation_id' => null,
            ],
            [
                'branch_id' => $product->branch_id,
                'code' => $product->code,
                'name' => $product->name,
            ],
        );
    }
}
