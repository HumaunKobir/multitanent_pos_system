<?php

use App\Models\Batch;
use App\Models\Branch;
use App\Models\Product;
use App\Models\User;
use Spatie\Permission\Models\Permission;

test('main branch user can find products assigned to operating branches in purchase search', function () {
    $this->artisan('permissions:sync');

    Branch::query()->firstOrCreate(
        ['id' => Branch::MAIN_BRANCH_ID],
        Branch::factory()->make(['name' => 'Main Branch'])->toArray(),
    );

    $operatingBranch = Branch::factory()->create();
    $mainUser = User::factory()->create(['branch_id' => Branch::MAIN_BRANCH_ID]);
    Permission::findOrCreate('inventory.purchase.create', 'web');
    $mainUser->givePermissionTo('inventory.purchase.create');

    $branchProduct = Product::factory()->create([
        'branch_id' => $operatingBranch->id,
        'name' => 'Branch Only Product '.fake()->unique()->numerify('###'),
    ]);

    Product::factory()->create([
        'branch_id' => Branch::MAIN_BRANCH_ID,
        'name' => 'Main Branch Product '.fake()->unique()->numerify('###'),
    ]);

    $response = $this->actingAs($mainUser)
        ->getJson('/api/products/for-purchase?search='.urlencode($branchProduct->name));

    $response->assertOk();

    $ids = collect($response->json())->pluck('id');

    expect($ids)->toContain($branchProduct->id);
});

test('operating branch user only sees their own products in purchase search', function () {
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

test('main branch user can find products with main branch stock in sell search', function () {
    $this->artisan('permissions:sync');

    Branch::query()->firstOrCreate(
        ['id' => Branch::MAIN_BRANCH_ID],
        Branch::factory()->make(['name' => 'Main Branch'])->toArray(),
    );

    $mainUser = User::factory()->create(['branch_id' => Branch::MAIN_BRANCH_ID]);
    Permission::findOrCreate('inventory.sell.create', 'web');
    $mainUser->givePermissionTo('inventory.sell.create');

    $product = Product::factory()->create([
        'name' => 'Main Stock Product '.fake()->unique()->numerify('###'),
    ]);
    Batch::factory()->for($product)->withStock(12)->create(['branch_id' => Branch::MAIN_BRANCH_ID]);

    $response = $this->actingAs($mainUser)
        ->getJson('/api/products/for-sell?search='.urlencode($product->name));

    $response->assertOk();

    $match = collect($response->json())->firstWhere('id', $product->id);

    expect($match)->not->toBeNull();
    expect((float) $match['stock'])->toBe(12.0);
});

test('main branch user can find products with main branch stock in distribution search', function () {
    $this->artisan('permissions:sync');

    Branch::query()->firstOrCreate(
        ['id' => Branch::MAIN_BRANCH_ID],
        Branch::factory()->make(['name' => 'Main Branch'])->toArray(),
    );

    $mainUser = User::factory()->create(['branch_id' => Branch::MAIN_BRANCH_ID]);
    Permission::findOrCreate('inventory.stock-distribution.create', 'web');
    $mainUser->givePermissionTo('inventory.stock-distribution.create');

    $product = Product::factory()->create([
        'name' => 'Distribute Product '.fake()->unique()->numerify('###'),
    ]);
    Batch::factory()->for($product)->withStock(20)->create(['branch_id' => Branch::MAIN_BRANCH_ID]);

    $response = $this->actingAs($mainUser)
        ->getJson('/api/products/for-distribution?search='.urlencode($product->name));

    $response->assertOk();

    $match = collect($response->json())->firstWhere('id', $product->id);

    expect($match)->not->toBeNull();
    expect((float) $match['stock'])->toBe(20.0);
});

test('main branch user can find legacy null branch stock in distribution search', function () {
    $this->artisan('permissions:sync');

    Branch::query()->firstOrCreate(
        ['id' => Branch::MAIN_BRANCH_ID],
        Branch::factory()->make(['name' => 'Main Branch'])->toArray(),
    );

    $mainUser = User::factory()->create(['branch_id' => Branch::MAIN_BRANCH_ID]);
    Permission::findOrCreate('inventory.stock-distribution.create', 'web');
    $mainUser->givePermissionTo('inventory.stock-distribution.create');

    $product = Product::factory()->create([
        'name' => 'Legacy Warehouse Product '.fake()->unique()->numerify('###'),
    ]);
    Batch::factory()->for($product)->withStock(18)->create(['branch_id' => null]);

    $response = $this->actingAs($mainUser)
        ->getJson('/api/products/for-distribution?search='.urlencode($product->name));

    $response->assertOk();

    $match = collect($response->json())->firstWhere('id', $product->id);

    expect($match)->not->toBeNull();
    expect((float) $match['stock'])->toBe(18.0);
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
