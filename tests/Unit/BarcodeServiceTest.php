<?php

use App\Models\Barcode;
use App\Models\Branch;
use App\Models\Product;
use App\Models\ProductVariation;
use App\Services\BarcodeService;
use Illuminate\Support\Str;
use Tests\TestCase;

uses(TestCase::class);

test('generated barcode is numeric and at most eight characters', function () {
    $branchId = Branch::resolveMainBranchId();

    $code = app(BarcodeService::class)->generateUniqueForBranch($branchId);

    expect($code)->toMatch('/^\d{7,8}$/')
        ->and(strlen($code))->toBeLessThanOrEqual(BarcodeService::MAX_LENGTH);
});

test('code exists in branch checks products variations and barcodes', function () {
    Branch::query()->firstOrCreate(
        ['id' => Branch::MAIN_BRANCH_ID],
        Branch::factory()->make(['name' => 'Main Branch'])->toArray(),
    );

    $branchId = Branch::resolveMainBranchId();
    $service = app(BarcodeService::class);
    $code = fake()->unique()->numerify('########');

    $product = Product::factory()->create([
        'branch_id' => $branchId,
        'code' => $code,
    ]);

    expect($service->codeExistsInBranch($branchId, $code))->toBeTrue()
        ->and($service->codeExistsInBranch($branchId, $code, excludeProductId: $product->id))->toBeFalse();

    $variation = ProductVariation::query()->create([
        'branch_id' => $branchId,
        'product_id' => $product->id,
        'sku' => fake()->unique()->numerify('########'),
        'price' => 100,
        'purchase_price' => 80,
        'stock' => 0,
        'variation_data' => ['label' => 'Test'],
    ]);

    expect($service->codeExistsInBranch($branchId, $variation->sku))->toBeTrue();

    Barcode::query()->create([
        'branch_id' => $branchId,
        'product_id' => $product->id,
        'product_variation_id' => null,
        'code' => fake()->unique()->numerify('########'),
        'name' => $product->name,
    ]);

    expect($service->codeExistsInBranch($branchId, Barcode::query()->latest('id')->value('code')))->toBeTrue();
});

test('resolve variation barcode keeps valid submitted sku', function () {
    $branchId = Branch::resolveMainBranchId();
    $service = app(BarcodeService::class);
    $sku = fake()->unique()->numerify('########');

    expect($service->resolveVariationBarcode($branchId, $sku))->toBe($sku);
});

test('shared product barcode is reused across branches but blocked globally for other products', function () {
    Branch::query()->firstOrCreate(
        ['id' => Branch::MAIN_BRANCH_ID],
        Branch::factory()->make(['name' => 'Main Branch'])->toArray(),
    );

    $mainBranchId = Branch::resolveMainBranchId();
    $otherBranch = Branch::factory()->create();
    $service = app(BarcodeService::class);
    $groupId = (string) Str::uuid();
    $sharedCode = fake()->unique()->numerify('########');

    Product::factory()->create([
        'branch_id' => $mainBranchId,
        'product_group_id' => $groupId,
        'code' => $sharedCode,
    ]);

    Product::factory()->create([
        'branch_id' => $otherBranch->id,
        'product_group_id' => $groupId,
        'code' => $sharedCode,
    ]);

    expect($service->codeIsAvailableGlobally($sharedCode, $groupId))->toBeTrue()
        ->and($service->codeIsAvailableGlobally($sharedCode))->toBeFalse();

    $nextCode = $service->generateUniqueSharedCode();

    expect($nextCode)->not->toBe($sharedCode)
        ->and($service->codeIsAvailableGlobally($nextCode))->toBeTrue();
});

test('resolve variation barcode generates when submitted sku is too long', function () {
    $branchId = Branch::resolveMainBranchId();
    $service = app(BarcodeService::class);

    $sku = $service->resolveVariationBarcode($branchId, 'TOOLONGSKU');

    expect(strlen($sku))->toBeLessThanOrEqual(BarcodeService::MAX_LENGTH);
});
