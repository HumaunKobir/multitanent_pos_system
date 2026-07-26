<?php

use App\Models\Batch;
use App\Models\Branch;
use App\Models\Product;
use App\Services\ProductBranchStockService;

test('branch warehouse stock excludes orphan batches without branch id', function () {
    $branch = Branch::factory()->create();
    $product = Product::factory()->create(['branch_id' => $branch->id]);

    Batch::factory()->create([
        'branch_id' => $branch->id,
        'product_id' => $product->id,
        'available' => 288,
    ]);

    Batch::factory()->create([
        'branch_id' => null,
        'product_id' => $product->id,
        'available' => 12,
    ]);

    $service = app(ProductBranchStockService::class);

    expect($service->currentStock($product->id, $branch->id))->toBe(288.0);
});
