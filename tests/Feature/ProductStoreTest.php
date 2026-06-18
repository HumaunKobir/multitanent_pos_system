<?php

use App\Enums\ProductLogType;
use App\Models\Barcode;
use App\Models\Batch;
use App\Models\Branch;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Color;
use App\Models\Ledger;
use App\Models\Product;
use App\Models\ProductInitialStock;
use App\Models\ProductInOutLog;
use App\Models\ProductVariation;
use App\Models\Size;
use App\Models\Transaction;
use App\Models\Unit;
use App\Models\User;

function productStoreAdmin(): User
{
    return User::factory()->create(['branch_id' => null]);
}

function validProductPayload(array $overrides = []): array
{
    Branch::query()->firstOrCreate(
        ['id' => Branch::MAIN_BRANCH_ID],
        Branch::factory()->make(['name' => 'Main Branch'])->toArray(),
    );

    return array_merge([
        'branch_id' => (string) Branch::MAIN_BRANCH_ID,
        'category_id' => (string) Category::factory()->create(['status' => 1])->id,
        'brand_id' => (string) Brand::factory()->create(['status' => 1])->id,
        'unit_id' => (string) Unit::query()->create([
            'branch_id' => Branch::MAIN_BRANCH_ID,
            'name' => 'Unit '.fake()->unique()->numerify('####'),
            'status' => 1,
        ])->id,
        'name' => 'Product '.fake()->unique()->numerify('######'),
        'purchase_price' => '100',
        'sale_price' => '150',
        'visible' => 'no',
        'status' => '1',
    ], $overrides);
}

test('product without manual code gets auto-generated code and barcode', function () {
    $admin = productStoreAdmin();
    $payload = validProductPayload(['code' => '']);

    $this->actingAs($admin)
        ->post(route('product.store'), $payload)
        ->assertRedirect(route('product.index'));

    $product = Product::query()->where('name', $payload['name'])->first();

    expect($product)->not->toBeNull()
        ->and($product->code)->toStartWith('PRD-');

    expect(
        Barcode::query()
            ->where('product_id', $product->id)
            ->whereNull('product_variation_id')
            ->where('code', $product->code)
            ->exists(),
    )->toBeTrue();
});

test('product with manual code uses provided code for barcode', function () {
    $admin = productStoreAdmin();
    $manualCode = 'MAN-'.fake()->unique()->numerify('######');
    $payload = validProductPayload(['code' => $manualCode]);

    $this->actingAs($admin)
        ->post(route('product.store'), $payload)
        ->assertRedirect(route('product.index'));

    $product = Product::query()->where('name', $payload['name'])->first();

    expect($product)->not->toBeNull()
        ->and($product->code)->toBe($manualCode);

    expect(
        Barcode::query()
            ->where('product_id', $product->id)
            ->whereNull('product_variation_id')
            ->where('code', $manualCode)
            ->exists(),
    )->toBeTrue();
});

test('product store saves multiple colors and sizes for non-variant product', function () {
    $admin = productStoreAdmin();
    $colorOne = Color::query()->create(['branch_id' => Branch::MAIN_BRANCH_ID, 'name' => 'Red '.fake()->unique()->numerify('####'), 'status' => 1]);
    $colorTwo = Color::query()->create(['branch_id' => Branch::MAIN_BRANCH_ID, 'name' => 'Blue '.fake()->unique()->numerify('####'), 'status' => 1]);
    $sizeOne = Size::query()->create(['branch_id' => Branch::MAIN_BRANCH_ID, 'name' => 'M '.fake()->unique()->numerify('####'), 'status' => 1]);
    $sizeTwo = Size::query()->create(['branch_id' => Branch::MAIN_BRANCH_ID, 'name' => 'L '.fake()->unique()->numerify('####'), 'status' => 1]);

    $payload = validProductPayload([
        'color_ids' => [(string) $colorOne->id, (string) $colorTwo->id],
        'size_ids' => [(string) $sizeOne->id, (string) $sizeTwo->id],
    ]);

    $this->actingAs($admin)
        ->post(route('product.store'), $payload)
        ->assertRedirect(route('product.index'));

    $product = Product::query()->where('name', $payload['name'])->first();

    expect($product)->not->toBeNull()
        ->and($product->colors)->toBe([$colorOne->id, $colorTwo->id])
        ->and($product->sizes)->toBe([$sizeOne->id, $sizeTwo->id]);
});

test('product store clears colors and sizes when variations are present', function () {
    $admin = productStoreAdmin();
    seedAccountingAccounts(branchId: Branch::MAIN_BRANCH_ID);
    $color = Color::query()->create(['name' => 'Blue '.fake()->unique()->numerify('####'), 'status' => 1]);
    $size = Size::query()->create(['name' => 'L '.fake()->unique()->numerify('####'), 'status' => 1]);

    $payload = validProductPayload([
        'color_ids' => [(string) $color->id],
        'size_ids' => [(string) $size->id],
        'purchase_price' => '0',
        'sale_price' => '0',
        'combinations' => [
            [
                'variant' => 'Blue-L',
                'variation_data' => ['label' => 'Blue-L', 'Color' => 'Blue', 'Size' => 'L'],
                'sale_price' => '200',
                'purchase_price' => '120',
                'sku' => 'VAR-'.fake()->unique()->numerify('######'),
                'stock' => '5',
            ],
        ],
    ]);

    $this->actingAs($admin)
        ->post(route('product.store'), $payload)
        ->assertRedirect(route('product.index'));

    $product = Product::query()->where('name', $payload['name'])->first();

    expect($product)->not->toBeNull()
        ->and($product->colors)->toBeNull()
        ->and($product->sizes)->toBeNull();
});

test('auto-generated product codes are unique', function () {
    $admin = productStoreAdmin();

    $firstPayload = validProductPayload(['code' => '']);
    $secondPayload = validProductPayload(['code' => '']);

    $this->actingAs($admin)->post(route('product.store'), $firstPayload)->assertRedirect();
    $this->actingAs($admin)->post(route('product.store'), $secondPayload)->assertRedirect();

    $first = Product::query()->where('name', $firstPayload['name'])->first();
    $second = Product::query()->where('name', $secondPayload['name'])->first();

    expect($first->code)->not->toBe($second->code);
});

test('all branches product creates isolated copy for each active branch', function () {
    $admin = productStoreAdmin();

    Branch::query()->firstOrCreate(
        ['id' => Branch::MAIN_BRANCH_ID],
        Branch::factory()->make(['name' => 'Main Branch'])->toArray(),
    );

    $operatingBranch = Branch::factory()->create();
    $productName = 'All Branch Product '.fake()->unique()->numerify('######');
    $manualCode = 'ALL-'.fake()->unique()->numerify('######');

    $payload = validProductPayload([
        'branch_id' => null,
        'name' => $productName,
        'code' => $manualCode,
    ]);

    $this->actingAs($admin)
        ->post(route('product.store'), $payload)
        ->assertRedirect(route('product.index'));

    $products = Product::query()->where('name', $productName)->get();
    $activeBranchCount = Branch::query()->active()->count();

    expect($products)->toHaveCount($activeBranchCount);

    $groupIds = $products->pluck('product_group_id')->filter()->unique();

    expect($groupIds)->toHaveCount(1);

    $mainBranchId = Branch::resolveMainBranchId();

    $mainCopy = $products->firstWhere('branch_id', $mainBranchId);
    $branchCopy = $products->firstWhere('branch_id', $operatingBranch->id);

    expect($mainCopy)->not->toBeNull()
        ->and($branchCopy)->not->toBeNull()
        ->and($mainCopy->code)->toBe($manualCode)
        ->and($branchCopy->code)->toBe($manualCode.'-B'.$operatingBranch->id)
        ->and($mainCopy->category_id)->not->toBe($branchCopy->category_id)
        ->and($mainCopy->category?->name)->toBe($branchCopy->category?->name)
        ->and($mainCopy->unit_id)->not->toBe($branchCopy->unit_id)
        ->and($mainCopy->unit?->name)->toBe($branchCopy->unit?->name);

    expect(
        Barcode::query()->where('product_id', $mainCopy->id)->where('code', $manualCode)->exists(),
    )->toBeTrue();

    expect(
        Barcode::query()->where('product_id', $branchCopy->id)->where('code', $branchCopy->code)->exists(),
    )->toBeTrue();
});

test('all branches product applies initial stock only to main branch copy', function () {
    $admin = productStoreAdmin();
    seedAccountingAccounts(branchId: Branch::MAIN_BRANCH_ID);

    Branch::query()->firstOrCreate(
        ['id' => Branch::MAIN_BRANCH_ID],
        Branch::factory()->make(['name' => 'Main Branch'])->toArray(),
    );

    $operatingBranch = Branch::factory()->create();
    $productName = 'Stock Scope Product '.fake()->unique()->numerify('######');

    $payload = validProductPayload([
        'branch_id' => null,
        'name' => $productName,
        'initial_stock' => '20',
        'purchase_price' => '50',
    ]);

    $this->actingAs($admin)
        ->post(route('product.store'), $payload)
        ->assertRedirect(route('product.index'));

    $products = Product::query()->where('name', $productName)->get();
    $mainBranchId = Branch::resolveMainBranchId();

    $mainCopy = $products->firstWhere('branch_id', $mainBranchId);
    $branchCopy = $products->firstWhere('branch_id', $operatingBranch->id);

    expect($mainCopy)->not->toBeNull()
        ->and($branchCopy)->not->toBeNull();

    $mainBatch = Batch::query()->where('product_id', $mainCopy->id)->first();
    $branchBatch = Batch::query()->where('product_id', $branchCopy->id)->first();

    expect($mainBatch)->not->toBeNull()
        ->and((float) $mainBatch->available)->toBe(20.0)
        ->and($branchBatch)->toBeNull();

    expect(
        ProductInitialStock::query()->where('product_id', $mainCopy->id)->exists(),
    )->toBeTrue();

    expect(
        ProductInitialStock::query()->where('product_id', $branchCopy->id)->exists(),
    )->toBeFalse();
});

test('all branches variation product applies initial stock only on main branch variations', function () {
    $admin = productStoreAdmin();
    seedAccountingAccounts(branchId: Branch::MAIN_BRANCH_ID);

    Branch::query()->firstOrCreate(
        ['id' => Branch::MAIN_BRANCH_ID],
        Branch::factory()->make(['name' => 'Main Branch'])->toArray(),
    );

    $operatingBranch = Branch::factory()->create();
    $productName = 'Variant Stock Scope '.fake()->unique()->numerify('######');
    $sku = 'VAR-'.fake()->unique()->numerify('######');

    $payload = validProductPayload([
        'branch_id' => null,
        'name' => $productName,
        'purchase_price' => '0',
        'sale_price' => '0',
        'initial_stock' => '8',
        'combinations' => [
            [
                'variant' => 'Blue-L',
                'variation_data' => ['label' => 'Blue-L', 'Color' => 'Blue', 'Size' => 'L'],
                'sale_price' => '200',
                'purchase_price' => '120',
                'sku' => $sku,
                'stock' => '',
            ],
        ],
    ]);

    $this->actingAs($admin)
        ->post(route('product.store'), $payload)
        ->assertRedirect(route('product.index'));

    $mainBranchId = Branch::resolveMainBranchId();
    $mainProduct = Product::query()->where('name', $productName)->where('branch_id', $mainBranchId)->first();
    $branchProduct = Product::query()->where('name', $productName)->where('branch_id', $operatingBranch->id)->first();

    $mainVariation = ProductVariation::query()->where('product_id', $mainProduct->id)->first();
    $branchVariation = ProductVariation::query()->where('product_id', $branchProduct->id)->first();

    expect($mainVariation->stock)->toBe(8)
        ->and($branchVariation->stock)->toBe(0);
});

test('product store creates batch initial stock for non-variant product', function () {
    $admin = productStoreAdmin();
    seedAccountingAccounts(branchId: Branch::MAIN_BRANCH_ID);
    $payload = validProductPayload([
        'initial_stock' => '25',
        'purchase_price' => '80',
    ]);

    $this->actingAs($admin)
        ->post(route('product.store'), $payload)
        ->assertRedirect(route('product.index'));

    $product = Product::query()->where('name', $payload['name'])->first();

    expect($product)->not->toBeNull();

    $batch = Batch::query()->where('product_id', $product->id)->first();

    expect($batch)->not->toBeNull()
        ->and((float) $batch->purchase_price)->toBe(80.0)
        ->and((float) $batch->available)->toBe(25.0);

    expect(
        ProductInOutLog::query()
            ->where('product_id', $product->id)
            ->where('type', ProductLogType::InitialStock->value)
            ->exists(),
    )->toBeTrue();

    $initialStockRecord = ProductInitialStock::query()
        ->where('product_id', $product->id)
        ->whereNull('product_variation_id')
        ->first();

    expect($initialStockRecord)->not->toBeNull()
        ->and($initialStockRecord->quantity)->toBe(25);

    $transaction = Transaction::query()
        ->where('source_type', ProductInitialStock::class)
        ->where('source_id', $initialStockRecord->id)
        ->first();

    expect($transaction)->not->toBeNull();

    $ledgers = Ledger::query()->where('transaction_id', $transaction->id)->get();
    expect(round($ledgers->sum('debit'), 2))->toBe(2000.0)
        ->and(round($ledgers->sum('credit'), 2))->toBe(2000.0);
});

test('product store applies global initial stock to variation combinations without stock', function () {
    $admin = productStoreAdmin();
    seedAccountingAccounts(branchId: Branch::MAIN_BRANCH_ID);
    $sku = 'VAR-'.fake()->unique()->numerify('######');

    $payload = validProductPayload([
        'purchase_price' => '0',
        'sale_price' => '0',
        'initial_stock' => '12',
        'combinations' => [
            [
                'variant' => 'Blue-L',
                'variation_data' => ['label' => 'Blue-L', 'Color' => 'Blue', 'Size' => 'L'],
                'sale_price' => '200',
                'purchase_price' => '120',
                'sku' => $sku,
                'stock' => '',
            ],
        ],
    ]);

    $this->actingAs($admin)
        ->post(route('product.store'), $payload)
        ->assertRedirect(route('product.index'));

    $product = Product::query()->where('name', $payload['name'])->first();
    $variation = ProductVariation::query()->where('product_id', $product->id)->first();

    expect($variation)->not->toBeNull()
        ->and($variation->stock)->toBe(12);
});

test('product store uses per-combination stock when provided for variations', function () {
    $admin = productStoreAdmin();
    seedAccountingAccounts(branchId: Branch::MAIN_BRANCH_ID);
    $sku = 'VAR-'.fake()->unique()->numerify('######');

    $payload = validProductPayload([
        'purchase_price' => '0',
        'sale_price' => '0',
        'initial_stock' => '99',
        'combinations' => [
            [
                'variant' => 'Red-M',
                'variation_data' => ['label' => 'Red-M', 'Color' => 'Red', 'Size' => 'M'],
                'sale_price' => '180',
                'purchase_price' => '110',
                'sku' => $sku,
                'stock' => '7',
            ],
        ],
    ]);

    $this->actingAs($admin)
        ->post(route('product.store'), $payload)
        ->assertRedirect(route('product.index'));

    $product = Product::query()->where('name', $payload['name'])->first();
    $variation = ProductVariation::query()->where('product_id', $product->id)->first();

    expect($variation)->not->toBeNull()
        ->and($variation->stock)->toBe(7);
});
