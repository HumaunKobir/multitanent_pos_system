<?php

use App\Enums\ProductLogType;
use App\Models\Batch;
use App\Models\Branch;
use App\Models\Product;
use App\Models\ProductInOutLog;
use App\Models\StockDistribution;
use App\Models\Transaction;
use App\Models\User;
use App\Support\AdminNavigation;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;

function mainBranchUser(array $permissions = []): User
{
    Branch::query()->firstOrCreate(
        ['id' => Branch::MAIN_BRANCH_ID],
        Branch::factory()->make(['name' => 'Main Branch'])->toArray(),
    );

    $user = User::factory()->create(['branch_id' => Branch::MAIN_BRANCH_ID]);

    foreach ($permissions as $permission) {
        Permission::findOrCreate($permission, 'web');
        $user->givePermissionTo($permission);
    }

    return $user;
}

test('operating branch user can view received stock index', function () {
    $this->artisan('permissions:sync');
    $this->withoutVite();

    $operatingBranch = Branch::factory()->create();
    $user = User::factory()->create(['branch_id' => $operatingBranch->id]);
    Permission::findOrCreate('inventory.stock-distribution.view', 'web');
    $user->givePermissionTo('inventory.stock-distribution.view');

    $this->actingAs($user)
        ->get('/inventory/stock-distribution')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/inventory/stock-distribution/index')
            ->where('isReceiverView', true)
            ->where('canManage', false));
});

test('main branch user can view stock distribution index', function () {
    $this->artisan('permissions:sync');
    $this->withoutVite();

    $user = mainBranchUser(['inventory.stock-distribution.view']);

    $this->actingAs($user)
        ->get('/inventory/stock-distribution')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/inventory/stock-distribution/index')
            ->has('distributions'));
});

test('main branch user can distribute stock to operating branch', function () {
    $this->artisan('permissions:sync');

    $user = mainBranchUser(['inventory.stock-distribution.create']);
    $targetBranch = Branch::factory()->create();

    $product = Product::factory()->create();
    $mainBatch = Batch::factory()->for($product)->withStock(25)->create([
        'branch_id' => Branch::MAIN_BRANCH_ID,
        'purchase_price' => 100,
    ]);

    $response = $this->actingAs($user)
        ->post('/inventory/stock-distribution', [
            'to_branch_id' => $targetBranch->id,
            'date' => now()->format('Y-m-d'),
            'comment' => 'Monthly allocation',
            'items' => [
                [
                    'product_id' => $product->id,
                    'variation_id' => null,
                    'quantity' => '8',
                ],
            ],
        ]);

    $distribution = StockDistribution::query()->latest('id')->first();

    expect($distribution)->not->toBeNull();
    $response->assertRedirect(route('inventory.stock-distribution.index'));

    expect($distribution->from_branch_id)->toBe(Branch::MAIN_BRANCH_ID);
    expect($distribution->to_branch_id)->toBe($targetBranch->id);
    expect($distribution->products)->toHaveCount(1);
    expect((float) $distribution->products->first()->quantity)->toBe(8.0);
    expect((float) $distribution->products->first()->main_stock_before)->toBe(25.0);

    $mainBatch->refresh();
    expect((float) $mainBatch->available)->toBe(17.0);

    $destinationBatch = Batch::query()
        ->where('product_id', $product->id)
        ->where('branch_id', $targetBranch->id)
        ->first();

    expect($destinationBatch)->not->toBeNull();
    expect((float) $destinationBatch->available)->toBe(8.0);

    expect(ProductInOutLog::query()->where('type', ProductLogType::Distribution_Out->value)->count())->toBeGreaterThan(0);
    expect(ProductInOutLog::query()->where('type', ProductLogType::Distribution_In->value)->count())->toBeGreaterThan(0);

    expect(Transaction::query()
        ->where('source_type', StockDistribution::class)
        ->where('source_id', $distribution->id)
        ->exists())->toBeTrue();
});

test('edit form includes current main branch stock for line items', function () {
    $this->artisan('permissions:sync');
    $this->withoutVite();

    $user = mainBranchUser([
        'inventory.stock-distribution.create',
        'inventory.stock-distribution.update',
    ]);
    $targetBranch = Branch::factory()->create();
    $product = Product::factory()->create();
    Batch::factory()->for($product)->withStock(20)->create([
        'branch_id' => Branch::MAIN_BRANCH_ID,
        'purchase_price' => 50,
    ]);

    $this->actingAs($user)
        ->post('/inventory/stock-distribution', [
            'to_branch_id' => $targetBranch->id,
            'date' => now()->format('Y-m-d'),
            'comment' => null,
            'items' => [
                ['product_id' => $product->id, 'variation_id' => null, 'quantity' => '7'],
            ],
        ])
        ->assertRedirect();

    $distribution = StockDistribution::query()->latest('id')->first();

    $this->actingAs($user)
        ->get("/inventory/stock-distribution/{$distribution->id}/edit")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/inventory/stock-distribution/edit')
            ->where('distribution.items.0.available_stock', 13)
            ->where('distribution.items.0.max_quantity', 20)
            ->where('distribution.items.0.quantity', '7'));
});

test('main branch user can update and delete a distribution', function () {
    $this->artisan('permissions:sync');

    seedAccountingAccounts();

    $user = mainBranchUser([
        'inventory.stock-distribution.create',
        'inventory.stock-distribution.update',
        'inventory.stock-distribution.delete',
    ]);
    $targetBranch = Branch::factory()->create();
    $otherBranch = Branch::factory()->create();

    $product = Product::factory()->create();
    Batch::factory()->for($product)->withStock(20)->create([
        'branch_id' => Branch::MAIN_BRANCH_ID,
        'purchase_price' => 50,
    ]);

    $this->actingAs($user)
        ->post('/inventory/stock-distribution', [
            'to_branch_id' => $targetBranch->id,
            'date' => now()->format('Y-m-d'),
            'comment' => 'Initial',
            'items' => [
                ['product_id' => $product->id, 'variation_id' => null, 'quantity' => '5'],
            ],
        ])
        ->assertRedirect();

    $distribution = StockDistribution::query()->latest('id')->first();

    $this->actingAs($user)
        ->put("/inventory/stock-distribution/{$distribution->id}", [
            'to_branch_id' => $otherBranch->id,
            'date' => now()->format('Y-m-d'),
            'comment' => 'Updated',
            'items' => [
                ['product_id' => $product->id, 'variation_id' => null, 'quantity' => '3'],
            ],
        ])
        ->assertRedirect(route('inventory.stock-distribution.index'));

    $distribution->refresh();
    expect($distribution->to_branch_id)->toBe($otherBranch->id);
    expect((float) $distribution->products->first()->quantity)->toBe(3.0);

    $destBatch = Batch::query()
        ->where('product_id', $product->id)
        ->where('branch_id', $otherBranch->id)
        ->first();
    expect((float) $destBatch->available)->toBe(3.0);

    $this->actingAs($user)
        ->delete("/inventory/stock-distribution/{$distribution->id}")
        ->assertRedirect(route('inventory.stock-distribution.index'));

    expect(StockDistribution::query()->whereKey($distribution->id)->exists())->toBeFalse();

    $mainBatch = Batch::query()
        ->where('product_id', $product->id)
        ->where('branch_id', Branch::MAIN_BRANCH_ID)
        ->first();
    expect((float) $mainBatch->available)->toBe(20.0);
});

test('main branch user can distribute legacy null branch warehouse stock', function () {
    $this->artisan('permissions:sync');

    $user = mainBranchUser(['inventory.stock-distribution.create']);
    $targetBranch = Branch::factory()->create();

    $product = Product::factory()->create();
    $mainBatch = Batch::factory()->for($product)->withStock(12)->create([
        'branch_id' => null,
        'purchase_price' => 80,
    ]);

    $this->actingAs($user)
        ->post('/inventory/stock-distribution', [
            'to_branch_id' => $targetBranch->id,
            'date' => now()->format('Y-m-d'),
            'comment' => null,
            'items' => [
                [
                    'product_id' => $product->id,
                    'variation_id' => null,
                    'quantity' => '4',
                ],
            ],
        ])
        ->assertRedirect();

    $mainBatch->refresh();
    expect((float) $mainBatch->available)->toBe(8.0);

    $destinationBatch = Batch::query()
        ->where('product_id', $product->id)
        ->where('branch_id', $targetBranch->id)
        ->first();

    expect($destinationBatch)->not->toBeNull();
    expect((float) $destinationBatch->available)->toBe(4.0);
});

test('branch user can sell stock after distribution', function () {
    $this->artisan('permissions:sync');

    $mainUser = mainBranchUser(['inventory.stock-distribution.create']);
    $targetBranch = Branch::factory()->create();
    $branchUser = User::factory()->create(['branch_id' => $targetBranch->id]);
    Permission::findOrCreate('inventory.sell.create', 'web');
    $branchUser->givePermissionTo('inventory.sell.create');

    $product = Product::factory()->create(['branch_id' => $targetBranch->id]);
    Batch::factory()->for($product)->withStock(10)->create([
        'branch_id' => Branch::MAIN_BRANCH_ID,
        'purchase_price' => 500,
    ]);

    $this->actingAs($mainUser)
        ->post('/inventory/stock-distribution', [
            'to_branch_id' => $targetBranch->id,
            'date' => now()->format('Y-m-d'),
            'comment' => null,
            'items' => [
                [
                    'product_id' => $product->id,
                    'variation_id' => null,
                    'quantity' => '5',
                ],
            ],
        ])
        ->assertRedirect();

    $sellResponse = $this->actingAs($branchUser)
        ->getJson('/api/products/for-sell?search='.urlencode($product->name));

    $sellResponse->assertOk();
    $match = collect($sellResponse->json())->firstWhere('id', $product->id);

    expect($match)->not->toBeNull();
    expect((float) $match['stock'])->toBe(5.0);
});

test('show page displays main stock snapshot for distributed products', function () {
    $this->artisan('permissions:sync');
    $this->withoutVite();

    $user = mainBranchUser([
        'inventory.stock-distribution.create',
        'inventory.stock-distribution.view',
    ]);
    $targetBranch = Branch::factory()->create(['name' => 'Gulshan Branch']);
    $product = Product::factory()->create(['name' => 'Test Product']);
    Batch::factory()->for($product)->withStock(50)->create([
        'branch_id' => Branch::MAIN_BRANCH_ID,
        'purchase_price' => 100,
    ]);

    $this->actingAs($user)
        ->post('/inventory/stock-distribution', [
            'to_branch_id' => $targetBranch->id,
            'date' => now()->format('Y-m-d'),
            'comment' => 'Branch allocation',
            'items' => [
                ['product_id' => $product->id, 'variation_id' => null, 'quantity' => '10'],
            ],
        ])
        ->assertRedirect();

    $distribution = StockDistribution::query()->latest('id')->first();

    $this->actingAs($user)
        ->get("/inventory/stock-distribution/{$distribution->id}")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/inventory/stock-distribution/show')
            ->where('distribution.invoice_number', $distribution->invoice_number)
            ->where('distribution.to_branch.name', 'Gulshan Branch')
            ->where('distribution.products.0.main_stock_before', 50)
            ->where('distribution.products.0.quantity', 10)
            ->where('distribution.products.0.main_stock_after', 40));
});

test('distribute stock menu is visible only for main branch users', function () {
    $this->artisan('permissions:sync');

    $mainUser = mainBranchUser([
        'inventory.stock-distribution.view',
        'inventory.purchase.view',
    ]);

    $operatingBranch = Branch::factory()->create();
    $branchUser = User::factory()->create([
        'branch_id' => $operatingBranch->id,
        'email' => 'branch-nav-'.uniqid().'@example.com',
    ]);
    Permission::findOrCreate('inventory.stock-distribution.view', 'web');
    Permission::findOrCreate('inventory.purchase.view', 'web');
    $branchUser->givePermissionTo(['inventory.stock-distribution.view', 'inventory.purchase.view']);

    $mainPurchases = collect(app(AdminNavigation::class)->build($mainUser))
        ->firstWhere('title', 'Purchases');

    $branchSales = collect(app(AdminNavigation::class)->build($branchUser))
        ->firstWhere('title', 'Sales');

    $mainChildren = collect($mainPurchases['children'] ?? [])->pluck('title');
    $branchChildren = collect($branchSales['children'] ?? [])->pluck('title');

    expect($mainChildren)->toContain('Distribute Stock');
    expect($branchChildren)->not->toContain('Distribute Stock');
    expect($branchChildren)->toContain('Received Stock');
});
