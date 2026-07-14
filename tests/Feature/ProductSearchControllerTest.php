<?php

use App\Models\Barcode;
use App\Models\Batch;
use App\Models\Branch;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariation;
use App\Models\User;
use App\Services\EcommerceBranchService;
use Spatie\Permission\Models\Permission;

test('purchase search returns latest products first', function () {
    $this->artisan('permissions:sync');

    $mainBranchId = ensureMainBranch();
    $mainUser = User::factory()->create(['branch_id' => $mainBranchId]);
    Permission::findOrCreate('inventory.purchase.create', 'web');
    $mainUser->givePermissionTo('inventory.purchase.create');

    $olderProduct = Product::factory()->create([
        'branch_id' => $mainBranchId,
        'name' => 'Older Purchase Product '.fake()->unique()->numerify('###'),
        'created_at' => now()->subDay(),
    ]);

    $newerProduct = Product::factory()->create([
        'branch_id' => $mainBranchId,
        'name' => 'Newer Purchase Product '.fake()->unique()->numerify('###'),
        'created_at' => now(),
    ]);

    $response = $this->actingAs($mainUser)
        ->getJson('/api/products/for-purchase?search=');

    $response->assertOk();

    $ids = collect($response->json())->pluck('id');

    expect($ids->search($newerProduct->id))->toBeLessThan($ids->search($olderProduct->id));
});

test('main branch user only sees main branch products in purchase search', function () {
    $this->artisan('permissions:sync');

    $mainBranchId = ensureMainBranch();

    $operatingBranch = Branch::factory()->create();
    $mainUser = User::factory()->create(['branch_id' => $mainBranchId]);
    Permission::findOrCreate('inventory.purchase.create', 'web');
    $mainUser->givePermissionTo('inventory.purchase.create');

    $branchProduct = Product::factory()->create([
        'branch_id' => $operatingBranch->id,
        'name' => 'Branch Only Product '.fake()->unique()->numerify('###'),
    ]);

    $mainProduct = Product::factory()->create([
        'branch_id' => $mainBranchId,
        'name' => 'Main Branch Product '.fake()->unique()->numerify('###'),
    ]);

    $response = $this->actingAs($mainUser)
        ->getJson('/api/products/for-purchase?search='.urlencode($mainProduct->name));

    $response->assertOk();

    $ids = collect($response->json())->pluck('id');

    expect($ids)->toContain($mainProduct->id);
    expect($ids)->not->toContain($branchProduct->id);
});

test('operating branch user does not see other branch products in purchase search', function () {
    $this->artisan('permissions:sync');

    $operatingBranch = Branch::factory()->create();
    $otherBranch = Branch::factory()->create();
    $branchUser = User::factory()->create(['branch_id' => $operatingBranch->id]);
    Permission::findOrCreate('inventory.purchase.create', 'web');
    $branchUser->givePermissionTo('inventory.purchase.create');

    $ownProduct = Product::factory()->create(['branch_id' => $operatingBranch->id]);
    $otherProduct = Product::factory()->create(['branch_id' => $otherBranch->id]);

    $response = $this->actingAs($branchUser)
        ->getJson('/api/products/for-purchase?search='.urlencode($ownProduct->name));

    $response->assertOk();

    $ids = collect($response->json())->pluck('id');

    expect($ids)->toContain($ownProduct->id);
    expect($ids)->not->toContain($otherProduct->id);
});

test('superadmin only sees main branch products in purchase search', function () {
    $this->artisan('permissions:sync');

    $mainBranchId = ensureMainBranch();
    $operatingBranch = Branch::factory()->create();
    $admin = User::factory()->create(['branch_id' => null]);
    Permission::findOrCreate('inventory.purchase.create', 'web');
    $admin->givePermissionTo('inventory.purchase.create');

    $mainProduct = Product::factory()->create([
        'branch_id' => $mainBranchId,
        'name' => 'Admin Purchase Product '.fake()->unique()->numerify('###'),
    ]);

    $operatingProduct = Product::factory()->create([
        'branch_id' => $operatingBranch->id,
        'name' => 'Other Branch Purchase Product '.fake()->unique()->numerify('###'),
    ]);

    $response = $this->actingAs($admin)
        ->getJson('/api/products/for-purchase?search='.urlencode($mainProduct->name));

    $response->assertOk();

    $ids = collect($response->json())->pluck('id');

    expect($ids)->toContain($mainProduct->id);
    expect($ids)->not->toContain($operatingProduct->id);
});

test('purchase search only includes variations for the resolved branch', function () {
    $this->artisan('permissions:sync');

    $mainBranchId = ensureMainBranch();
    $operatingBranch = Branch::factory()->create();
    $admin = User::factory()->create(['branch_id' => null]);
    Permission::findOrCreate('inventory.purchase.create', 'web');
    $admin->givePermissionTo('inventory.purchase.create');

    $product = Product::factory()->create([
        'branch_id' => $mainBranchId,
        'name' => 'Variant Purchase Product '.fake()->unique()->numerify('###'),
    ]);

    $mainVariation = ProductVariation::query()->create([
        'product_id' => $product->id,
        'branch_id' => $mainBranchId,
        'sku' => 'MAIN-'.fake()->unique()->numerify('####'),
        'price' => 150,
        'purchase_price' => 100,
        'stock' => 0,
        'variation_data' => ['label' => 'Main Red'],
    ]);

    ProductVariation::query()->create([
        'product_id' => $product->id,
        'branch_id' => $operatingBranch->id,
        'sku' => 'OTHER-'.fake()->unique()->numerify('####'),
        'price' => 150,
        'purchase_price' => 100,
        'stock' => 0,
        'variation_data' => ['label' => 'Other Blue'],
    ]);

    $response = $this->actingAs($admin)
        ->getJson('/api/products/for-purchase?search='.urlencode($product->name));

    $response->assertOk();

    $match = collect($response->json())->firstWhere('id', $product->id);

    expect($match)->not->toBeNull()
        ->and(collect($match['variations'])->pluck('id'))->toContain($mainVariation->id)
        ->and(collect($match['variations'])->pluck('label'))->not->toContain('Other Blue');
});

test('purchase search returns branch stock for simple and variant products', function () {
    $this->artisan('permissions:sync');

    $mainBranchId = ensureMainBranch();
    $mainUser = User::factory()->create(['branch_id' => $mainBranchId]);
    Permission::findOrCreate('inventory.purchase.create', 'web');
    $mainUser->givePermissionTo('inventory.purchase.create');

    $simpleProduct = Product::factory()->create([
        'branch_id' => $mainBranchId,
        'name' => 'Purchase Stock Product '.fake()->unique()->numerify('###'),
    ]);
    Batch::factory()->for($simpleProduct)->withStock(15)->create(['branch_id' => $mainBranchId]);

    $variantProduct = Product::factory()->create([
        'branch_id' => $mainBranchId,
        'name' => 'Purchase Variant Stock Product '.fake()->unique()->numerify('###'),
    ]);

    $redVariation = ProductVariation::query()->create([
        'product_id' => $variantProduct->id,
        'branch_id' => $mainBranchId,
        'sku' => 'PUR-RED-'.fake()->unique()->numerify('####'),
        'price' => 150,
        'purchase_price' => 100,
        'stock' => 7,
        'variation_data' => ['label' => 'Red'],
    ]);

    ProductVariation::query()->create([
        'product_id' => $variantProduct->id,
        'branch_id' => $mainBranchId,
        'sku' => 'PUR-BLUE-'.fake()->unique()->numerify('####'),
        'price' => 150,
        'purchase_price' => 100,
        'stock' => 4,
        'variation_data' => ['label' => 'Blue'],
    ]);

    $simpleResponse = $this->actingAs($mainUser)
        ->getJson('/api/products/for-purchase?search='.urlencode($simpleProduct->name));

    $simpleResponse->assertOk();

    $simpleMatch = collect($simpleResponse->json())->firstWhere('id', $simpleProduct->id);

    expect($simpleMatch)->not->toBeNull()
        ->and((float) $simpleMatch['stock'])->toBe(15.0);

    $variantResponse = $this->actingAs($mainUser)
        ->getJson('/api/products/for-purchase?search='.urlencode($variantProduct->name));

    $variantResponse->assertOk();

    $variantMatch = collect($variantResponse->json())->firstWhere('id', $variantProduct->id);
    $redMatch = collect($variantMatch['variations'])->firstWhere('id', $redVariation->id);

    expect($variantMatch)->not->toBeNull()
        ->and((float) $redMatch['stock'])->toBe(7.0);
});

test('purchase search finds product by barcode and returns barcode payload', function () {
    $this->artisan('permissions:sync');

    $mainBranchId = ensureMainBranch();
    $mainUser = User::factory()->create(['branch_id' => $mainBranchId]);
    Permission::findOrCreate('inventory.purchase.create', 'web');
    $mainUser->givePermissionTo('inventory.purchase.create');

    $barcodeCode = 'BC'.fake()->unique()->numerify('######');
    $product = Product::factory()->create([
        'branch_id' => $mainBranchId,
        'name' => 'Purchase Barcode Product '.fake()->unique()->numerify('###'),
        'code' => 'PRD-'.fake()->unique()->numerify('####'),
    ]);

    Barcode::query()->create([
        'branch_id' => $mainBranchId,
        'product_id' => $product->id,
        'product_variation_id' => null,
        'code' => $barcodeCode,
        'name' => $product->name,
    ]);

    $response = $this->actingAs($mainUser)
        ->getJson('/api/products/for-purchase?search='.urlencode($barcodeCode));

    $response->assertOk();

    $match = collect($response->json())->firstWhere('id', $product->id);

    expect($match)->not->toBeNull()
        ->and($match['barcodes'])->toBeArray()
        ->and(collect($match['barcodes'])->pluck('code'))->toContain($barcodeCode);
});

test('purchase search finds variant by variation barcode', function () {
    $this->artisan('permissions:sync');

    $mainBranchId = ensureMainBranch();
    $mainUser = User::factory()->create(['branch_id' => $mainBranchId]);
    Permission::findOrCreate('inventory.purchase.create', 'web');
    $mainUser->givePermissionTo('inventory.purchase.create');

    $product = Product::factory()->create([
        'branch_id' => $mainBranchId,
        'name' => 'Purchase Variant Barcode Product '.fake()->unique()->numerify('###'),
    ]);

    $variation = ProductVariation::query()->create([
        'product_id' => $product->id,
        'branch_id' => $mainBranchId,
        'sku' => 'SKU-'.fake()->unique()->numerify('####'),
        'price' => 150,
        'purchase_price' => 100,
        'stock' => 5,
        'variation_data' => ['label' => 'Large'],
    ]);

    $barcodeCode = 'VB'.fake()->unique()->numerify('######');

    Barcode::query()->create([
        'branch_id' => $mainBranchId,
        'product_id' => $product->id,
        'product_variation_id' => $variation->id,
        'code' => $barcodeCode,
        'name' => $product->name.' Large',
    ]);

    $response = $this->actingAs($mainUser)
        ->getJson('/api/products/for-purchase?search='.urlencode($barcodeCode));

    $response->assertOk();

    $match = collect($response->json())->firstWhere('id', $product->id);
    $barcodeMatch = collect($match['barcodes'] ?? [])->firstWhere('code', $barcodeCode);

    expect($match)->not->toBeNull()
        ->and($barcodeMatch)->not->toBeNull()
        ->and((int) $barcodeMatch['product_variation_id'])->toBe($variation->id);
});

test('sell search can filter products by category', function () {
    $this->artisan('permissions:sync');

    ensureMainBranch();

    $mainBranchId = Branch::resolveMainBranchId();
    $mainUser = User::factory()->create(['branch_id' => $mainBranchId]);
    Permission::findOrCreate('inventory.sell.create', 'web');
    $mainUser->givePermissionTo('inventory.sell.create');

    $categoryA = Category::factory()->create(['name' => 'Electronics '.fake()->unique()->numerify('###')]);
    $categoryB = Category::factory()->create(['name' => 'Groceries '.fake()->unique()->numerify('###')]);

    $productA = Product::factory()->create([
        'branch_id' => Branch::resolveMainBranchId(),
        'category_id' => $categoryA->id,
        'name' => 'Category A Product '.fake()->unique()->numerify('###'),
    ]);
    $productB = Product::factory()->create([
        'branch_id' => Branch::resolveMainBranchId(),
        'category_id' => $categoryB->id,
        'name' => 'Category B Product '.fake()->unique()->numerify('###'),
    ]);

    Batch::factory()->for($productA)->withStock(5)->create(['branch_id' => Branch::resolveMainBranchId()]);
    Batch::factory()->for($productB)->withStock(5)->create(['branch_id' => Branch::resolveMainBranchId()]);

    $response = $this->actingAs($mainUser)
        ->getJson('/api/products/for-sell?category_id='.$categoryA->id);

    $response->assertOk();

    $ids = collect($response->json())->pluck('id');

    expect($ids)->toContain($productA->id);
    expect($ids)->not->toContain($productB->id);

    $match = collect($response->json())->firstWhere('id', $productA->id);

    expect($match['category_id'])->toBe($categoryA->id);
    expect($match['category_name'])->toBe($categoryA->name);
});

test('sell browse returns products when no search or category filter', function () {
    $this->artisan('permissions:sync');

    ensureMainBranch();

    $mainBranchId = Branch::resolveMainBranchId();
    $mainUser = User::factory()->create(['branch_id' => $mainBranchId]);
    Permission::findOrCreate('inventory.sell.create', 'web');
    $mainUser->givePermissionTo('inventory.sell.create');

    $product = Product::factory()->create([
        'branch_id' => Branch::resolveMainBranchId(),
        'name' => 'Browse All Product '.fake()->unique()->numerify('###'),
    ]);
    Batch::factory()->for($product)->withStock(8)->create(['branch_id' => Branch::resolveMainBranchId()]);

    $response = $this->actingAs($mainUser)
        ->getJson('/api/products/for-sell?search='.urlencode($product->name));

    $response->assertOk();

    expect(collect($response->json())->pluck('id'))->toContain($product->id);
});

test('main branch user can find products with main branch stock in sell search', function () {
    $this->artisan('permissions:sync');

    ensureMainBranch();

    $mainBranchId = Branch::resolveMainBranchId();
    $mainUser = User::factory()->create(['branch_id' => $mainBranchId]);
    Permission::findOrCreate('inventory.sell.create', 'web');
    $mainUser->givePermissionTo('inventory.sell.create');

    $product = Product::factory()->create([
        'branch_id' => Branch::resolveMainBranchId(),
        'name' => 'Main Stock Product '.fake()->unique()->numerify('###'),
    ]);
    Batch::factory()->for($product)->withStock(12)->create(['branch_id' => Branch::resolveMainBranchId()]);

    $response = $this->actingAs($mainUser)
        ->getJson('/api/products/for-sell?search='.urlencode($product->name));

    $response->assertOk();

    $match = collect($response->json())->firstWhere('id', $product->id);

    expect($match)->not->toBeNull();
    expect((float) $match['stock'])->toBe(12.0);
});

test('superadmin only sees main branch products in sell search', function () {
    $this->artisan('permissions:sync');

    $mainBranchId = ensureMainBranch();
    $operatingBranch = Branch::factory()->create();
    $admin = User::factory()->create(['branch_id' => null]);
    Permission::findOrCreate('inventory.sell.create', 'web');
    $admin->givePermissionTo('inventory.sell.create');

    $mainProduct = Product::factory()->create([
        'branch_id' => $mainBranchId,
        'name' => 'Admin Sell Product '.fake()->unique()->numerify('###'),
    ]);
    Batch::factory()->for($mainProduct)->withStock(5)->create(['branch_id' => $mainBranchId]);

    $operatingProduct = Product::factory()->create([
        'branch_id' => $operatingBranch->id,
        'name' => 'Other Branch Sell Product '.fake()->unique()->numerify('###'),
    ]);
    Batch::factory()->for($operatingProduct)->withStock(5)->create(['branch_id' => $operatingBranch->id]);

    $response = $this->actingAs($admin)
        ->getJson('/api/products/for-sell?search='.urlencode($mainProduct->name));

    $response->assertOk();

    $ids = collect($response->json())->pluck('id');

    expect($ids)->toContain($mainProduct->id);
    expect($ids)->not->toContain($operatingProduct->id);
});

test('sell search only includes stock and variations for the resolved branch', function () {
    $this->artisan('permissions:sync');

    $mainBranchId = ensureMainBranch();
    $operatingBranch = Branch::factory()->create();
    $admin = User::factory()->create(['branch_id' => null]);
    Permission::findOrCreate('inventory.sell.create', 'web');
    $admin->givePermissionTo('inventory.sell.create');

    $product = Product::factory()->create([
        'branch_id' => $mainBranchId,
        'name' => 'Branch Stock Sell Product '.fake()->unique()->numerify('###'),
    ]);

    Batch::factory()->for($product)->withStock(8)->create(['branch_id' => $mainBranchId]);
    Batch::factory()->for($product)->withStock(20)->create(['branch_id' => $operatingBranch->id]);

    $mainVariation = ProductVariation::query()->create([
        'product_id' => $product->id,
        'branch_id' => $mainBranchId,
        'sku' => 'SELL-MAIN-'.fake()->unique()->numerify('####'),
        'price' => 150,
        'purchase_price' => 100,
        'stock' => 3,
        'variation_data' => ['label' => 'Main Sell Red'],
    ]);

    ProductVariation::query()->create([
        'product_id' => $product->id,
        'branch_id' => $operatingBranch->id,
        'sku' => 'SELL-OTHER-'.fake()->unique()->numerify('####'),
        'price' => 150,
        'purchase_price' => 100,
        'stock' => 9,
        'variation_data' => ['label' => 'Other Sell Blue'],
    ]);

    $response = $this->actingAs($admin)
        ->getJson('/api/products/for-sell?search='.urlencode($product->name));

    $response->assertOk();

    $match = collect($response->json())->firstWhere('id', $product->id);

    expect($match)->not->toBeNull()
        ->and((float) $match['stock'])->toBe(8.0)
        ->and(collect($match['variations'])->pluck('id'))->toContain($mainVariation->id)
        ->and(collect($match['variations'])->pluck('label'))->not->toContain('Other Sell Blue');
});

test('main branch user can find legacy null branch stock in sell search', function () {
    $this->artisan('permissions:sync');

    $mainBranchId = ensureMainBranch();
    $mainUser = User::factory()->create(['branch_id' => $mainBranchId]);
    Permission::findOrCreate('inventory.sell.create', 'web');
    $mainUser->givePermissionTo('inventory.sell.create');

    $product = Product::factory()->create([
        'branch_id' => $mainBranchId,
        'name' => 'Legacy Sell Product '.fake()->unique()->numerify('###'),
    ]);
    Batch::factory()->for($product)->withStock(14)->create(['branch_id' => null]);

    $response = $this->actingAs($mainUser)
        ->getJson('/api/products/for-sell?search='.urlencode($product->name));

    $response->assertOk();

    $match = collect($response->json())->firstWhere('id', $product->id);

    expect($match)->not->toBeNull();
    expect((float) $match['stock'])->toBe(14.0);
});

test('main branch user can find products with main branch stock in distribution search', function () {
    $this->artisan('permissions:sync');

    $mainBranchId = ensureMainBranch();

    $admin = User::factory()->create(['branch_id' => null]);
    Permission::findOrCreate('inventory.stock-distribution.create', 'web');
    $admin->givePermissionTo('inventory.stock-distribution.create');

    $product = Product::factory()->create([
        'branch_id' => $mainBranchId,
        'name' => 'Distribute Product '.fake()->unique()->numerify('###'),
    ]);
    Batch::factory()->for($product)->withStock(20)->create(['branch_id' => $mainBranchId]);

    $response = $this->actingAs($admin)
        ->getJson('/api/products/for-distribution?search='.urlencode($product->name));

    $response->assertOk();

    $match = collect($response->json())->firstWhere('id', $product->id);

    expect($match)->not->toBeNull();
    expect((float) $match['stock'])->toBe(20.0);
});

test('main branch user can find legacy null branch stock in distribution search', function () {
    $this->artisan('permissions:sync');

    $mainBranchId = ensureMainBranch();

    $admin = User::factory()->create(['branch_id' => null]);
    Permission::findOrCreate('inventory.stock-distribution.create', 'web');
    $admin->givePermissionTo('inventory.stock-distribution.create');

    $product = Product::factory()->create([
        'branch_id' => $mainBranchId,
        'name' => 'Legacy Warehouse Product '.fake()->unique()->numerify('###'),
    ]);
    Batch::factory()->for($product)->withStock(18)->create(['branch_id' => null]);

    $response = $this->actingAs($admin)
        ->getJson('/api/products/for-distribution?search='.urlencode($product->name));

    $response->assertOk();

    $match = collect($response->json())->firstWhere('id', $product->id);

    expect($match)->not->toBeNull();
    expect((float) $match['stock'])->toBe(18.0);
});

test('distribution search finds products at resolved main branch warehouse', function () {
    $this->artisan('permissions:sync');

    EcommerceBranchService::resetResolvedId();

    Branch::query()->firstOrCreate(
        ['name' => Branch::ECOMMERCE_BRANCH_NAME],
        Branch::factory()->make(['name' => Branch::ECOMMERCE_BRANCH_NAME])->toArray(),
    );

    Branch::query()->firstOrCreate(
        ['name' => Branch::MAIN_BRANCH_NAME],
        Branch::factory()->make(['name' => Branch::MAIN_BRANCH_NAME])->toArray(),
    );

    EcommerceBranchService::resetResolvedId();

    $mainBranchId = Branch::resolveMainBranchId();

    $admin = User::factory()->create(['branch_id' => null]);
    Permission::findOrCreate('inventory.stock-distribution.create', 'web');
    $admin->givePermissionTo('inventory.stock-distribution.create');

    $product = Product::factory()->create([
        'branch_id' => $mainBranchId,
        'name' => 'Resolved Warehouse Product '.fake()->unique()->numerify('###'),
    ]);
    Batch::factory()->for($product)->withStock(20)->create(['branch_id' => $mainBranchId]);

    $response = $this->actingAs($admin)
        ->getJson('/api/products/for-distribution?search='.urlencode($product->name));

    $response->assertOk();

    $match = collect($response->json())->firstWhere('id', $product->id);

    expect($match)->not->toBeNull();
    expect((float) $match['stock'])->toBe(20.0);
});

test('distribution browse returns all in-stock main branch products', function () {
    $this->artisan('permissions:sync');

    $mainBranchId = ensureMainBranch();

    $admin = User::factory()->create(['branch_id' => null]);
    Permission::findOrCreate('inventory.stock-distribution.create', 'web');
    $admin->givePermissionTo('inventory.stock-distribution.create');

    $inStockProducts = collect();

    for ($i = 0; $i < 18; $i++) {
        $product = Product::factory()->create([
            'branch_id' => $mainBranchId,
            'name' => 'Browse Stock Product '.fake()->unique()->numerify('###'),
        ]);

        Batch::factory()->for($product)->withStock(5)->create(['branch_id' => $mainBranchId]);
        $inStockProducts->push($product);
    }

    $outOfStockProduct = Product::factory()->create([
        'branch_id' => $mainBranchId,
        'name' => 'Empty Stock Product '.fake()->unique()->numerify('###'),
    ]);

    $response = $this->actingAs($admin)
        ->getJson('/api/products/for-distribution?search=');

    $response->assertOk();

    $ids = collect($response->json())->pluck('id');

    foreach ($inStockProducts as $product) {
        expect($ids)->toContain($product->id);
    }

    expect($ids)->not->toContain($outOfStockProduct->id);
});

test('main branch user with branch_id can access distribution product search', function () {
    $this->artisan('permissions:sync');

    $mainBranchId = ensureMainBranch();

    $admin = User::factory()->create(['branch_id' => $mainBranchId]);
    Permission::findOrCreate('inventory.stock-distribution.create', 'web');
    $admin->givePermissionTo('inventory.stock-distribution.create');

    $product = Product::factory()->create([
        'branch_id' => $mainBranchId,
        'name' => 'Main Branch Admin Product '.fake()->unique()->numerify('###'),
    ]);
    Batch::factory()->for($product)->withStock(12)->create(['branch_id' => $mainBranchId]);

    $response = $this->actingAs($admin)
        ->getJson('/api/products/for-distribution?search='.urlencode($product->name));

    $response->assertOk();

    $match = collect($response->json())->firstWhere('id', $product->id);

    expect($match)->not->toBeNull();
    expect((float) $match['stock'])->toBe(12.0);
});

test('distribution search excludes operating branch products even when they have stock', function () {
    $this->artisan('permissions:sync');

    $mainBranchId = ensureMainBranch();
    $operatingBranch = Branch::factory()->create();

    $admin = User::factory()->create(['branch_id' => $mainBranchId]);
    Permission::findOrCreate('inventory.stock-distribution.create', 'web');
    $admin->givePermissionTo('inventory.stock-distribution.create');

    $branchProduct = Product::factory()->create([
        'branch_id' => $operatingBranch->id,
        'name' => 'Operating Branch Only Product '.fake()->unique()->numerify('###'),
    ]);
    Batch::factory()->for($branchProduct)->withStock(30)->create(['branch_id' => $operatingBranch->id]);

    $response = $this->actingAs($admin)
        ->getJson('/api/products/for-distribution?search='.urlencode($branchProduct->name));

    $response->assertOk();

    expect(collect($response->json())->pluck('id'))->not->toContain($branchProduct->id);
});

test('operating branch user cannot access distribution product search', function () {
    $this->artisan('permissions:sync');

    $operatingBranch = Branch::factory()->create();
    $branchUser = User::factory()->create(['branch_id' => $operatingBranch->id]);
    Permission::findOrCreate('inventory.stock-distribution.create', 'web');
    $branchUser->givePermissionTo('inventory.stock-distribution.create');

    $this->actingAs($branchUser)
        ->getJson('/api/products/for-distribution?search=')
        ->assertForbidden();
});
