<?php

use App\Enums\ProductLogType;
use App\Models\Batch;
use App\Models\Branch;
use App\Models\Product;
use App\Models\ProductInOutLog;
use App\Models\StockDistribution;
use App\Models\Transaction;
use App\Models\User;
use App\Services\EcommerceBranchService;
use App\Support\AdminNavigation;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;

function mainBranchUser(array $permissions = []): User
{
    $mainBranchId = ensureMainBranch();

    seedAccountingAccounts(branchId: $mainBranchId);

    $user = User::factory()->create(['branch_id' => $mainBranchId]);

    foreach ($permissions as $permission) {
        Permission::findOrCreate($permission, 'web');
        $user->givePermissionTo($permission);
    }

    return $user;
}

function superAdminUser(array $permissions = []): User
{
    ensureMainBranch();

    seedAccountingAccounts(branchId: Branch::resolveMainBranchId());

    $user = User::factory()->create(['branch_id' => null]);

    foreach ($permissions as $permission) {
        Permission::findOrCreate($permission, 'web');
        $user->givePermissionTo($permission);
    }

    return $user;
}

test('operating branch user cannot access stock distribution', function () {
    $this->artisan('permissions:sync');
    $this->withoutVite();

    $operatingBranch = Branch::factory()->create();
    $user = User::factory()->create(['branch_id' => $operatingBranch->id]);
    Permission::findOrCreate('inventory.stock-distribution.view', 'web');
    $user->givePermissionTo('inventory.stock-distribution.view');

    $this->actingAs($user)
        ->get('/inventory/stock-distribution')
        ->assertNotFound();
});

test('main branch user cannot access stock distribution', function () {
    $this->artisan('permissions:sync');
    $this->withoutVite();

    $user = mainBranchUser(['inventory.stock-distribution.view']);

    $this->actingAs($user)
        ->get('/inventory/stock-distribution')
        ->assertNotFound();
});

test('super admin can view stock distribution index', function () {
    $this->artisan('permissions:sync');
    $this->withoutVite();

    $user = superAdminUser(['inventory.stock-distribution.view']);

    $this->actingAs($user)
        ->get('/inventory/stock-distribution')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/inventory/stock-distribution/index')
            ->has('distributions'));
});

test('super admin can distribute stock to operating branch', function () {
    $this->artisan('permissions:sync');

    $user = superAdminUser(['inventory.stock-distribution.create']);
    $targetBranch = Branch::factory()->create();

    $product = Product::factory()->create(['branch_id' => Branch::resolveMainBranchId()]);
    $mainBatch = Batch::factory()->for($product)->withStock(25)->create([
        'branch_id' => Branch::resolveMainBranchId(),
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

    expect($distribution->from_branch_id)->toBe(Branch::resolveMainBranchId());
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

    $user = superAdminUser([
        'inventory.stock-distribution.create',
        'inventory.stock-distribution.update',
    ]);
    $targetBranch = Branch::factory()->create();
    $product = Product::factory()->create(['branch_id' => Branch::resolveMainBranchId()]);
    Batch::factory()->for($product)->withStock(20)->create([
        'branch_id' => Branch::resolveMainBranchId(),
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

    $user = superAdminUser([
        'inventory.stock-distribution.create',
        'inventory.stock-distribution.update',
        'inventory.stock-distribution.delete',
    ]);
    $targetBranch = Branch::factory()->create();
    $otherBranch = Branch::factory()->create();

    $product = Product::factory()->create(['branch_id' => Branch::resolveMainBranchId()]);
    Batch::factory()->for($product)->withStock(20)->create([
        'branch_id' => Branch::resolveMainBranchId(),
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
        ->where('branch_id', Branch::resolveMainBranchId())
        ->first();
    expect((float) $mainBatch->available)->toBe(20.0);
});

test('main branch user can distribute legacy null branch warehouse stock', function () {
    $this->artisan('permissions:sync');

    $user = superAdminUser(['inventory.stock-distribution.create']);
    $targetBranch = Branch::factory()->create();

    $product = Product::factory()->create(['branch_id' => Branch::resolveMainBranchId()]);
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

    $admin = superAdminUser(['inventory.stock-distribution.create']);
    $targetBranch = Branch::factory()->create();
    $branchUser = User::factory()->create(['branch_id' => $targetBranch->id]);
    Permission::findOrCreate('inventory.sell.create', 'web');
    $branchUser->givePermissionTo('inventory.sell.create');

    $groupId = (string) Str::uuid();
    $mainProduct = Product::factory()->create([
        'branch_id' => Branch::resolveMainBranchId(),
        'product_group_id' => $groupId,
        'name' => 'Distributed Sell Product '.fake()->unique()->numerify('###'),
    ]);
    $branchProduct = Product::factory()->create([
        'branch_id' => $targetBranch->id,
        'product_group_id' => $groupId,
        'name' => $mainProduct->name,
    ]);

    Batch::factory()->for($mainProduct)->withStock(10)->create([
        'branch_id' => Branch::resolveMainBranchId(),
        'purchase_price' => 500,
    ]);

    $this->actingAs($admin)
        ->post('/inventory/stock-distribution', [
            'to_branch_id' => $targetBranch->id,
            'date' => now()->format('Y-m-d'),
            'comment' => null,
            'items' => [
                [
                    'product_id' => $mainProduct->id,
                    'variation_id' => null,
                    'quantity' => '5',
                ],
            ],
        ])
        ->assertRedirect();

    $sellResponse = $this->actingAs($branchUser)
        ->getJson('/api/products/for-sell?search='.urlencode($branchProduct->name));

    $sellResponse->assertOk();
    $match = collect($sellResponse->json())->firstWhere('id', $branchProduct->id);

    expect($match)->not->toBeNull();
    expect((float) $match['stock'])->toBe(5.0);
});

test('show page displays main stock snapshot for distributed products', function () {
    $this->artisan('permissions:sync');
    $this->withoutVite();

    $user = superAdminUser([
        'inventory.stock-distribution.create',
        'inventory.stock-distribution.view',
    ]);
    $targetBranch = Branch::factory()->create(['name' => 'Gulshan Branch '.fake()->unique()->numerify('###')]);
    $product = Product::factory()->create(['branch_id' => Branch::resolveMainBranchId(), 'name' => 'Test Product']);
    Batch::factory()->for($product)->withStock(50)->create([
        'branch_id' => Branch::resolveMainBranchId(),
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

    $distribution = StockDistribution::query()
        ->where('comment', 'Branch allocation')
        ->latest('id')
        ->first();

    $this->actingAs($user)
        ->get("/inventory/stock-distribution/{$distribution->id}")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/inventory/stock-distribution/show')
            ->where('distribution.invoice_number', $distribution->invoice_number)
            ->where('distribution.to_branch.name', $targetBranch->name)
            ->where('distribution.products.0.main_stock_before', 50)
            ->where('distribution.products.0.quantity', 10)
            ->where('distribution.products.0.main_stock_after', 40));
});

test('distribute stock menu is visible only for super admin', function () {
    $this->artisan('permissions:sync');

    $admin = superAdminUser([
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
    Permission::findOrCreate('inventory.sell.view', 'web');
    $branchUser->givePermissionTo(['inventory.stock-distribution.view', 'inventory.purchase.view', 'inventory.sell.view']);

    $adminPurchases = collect(app(AdminNavigation::class)->build($admin))
        ->firstWhere('title', 'Purchases');

    $branchSales = collect(app(AdminNavigation::class)->build($branchUser))
        ->firstWhere('title', 'Sales');

    $adminChildren = collect($adminPurchases['children'] ?? [])->pluck('title');
    $branchChildren = collect($branchSales['children'] ?? [])->pluck('title');

    expect($adminChildren)->toContain('Distribute Stock');
    expect($branchChildren)->not->toContain('Distribute Stock');
    expect($branchChildren)->not->toContain('Received Stock');
});

test('distribution maps stock to destination branch product copy', function () {
    $this->artisan('permissions:sync');

    $user = superAdminUser(['inventory.stock-distribution.create']);
    $targetBranch = Branch::factory()->create();
    $groupId = (string) Str::uuid();

    $mainProduct = Product::factory()->create([
        'branch_id' => Branch::resolveMainBranchId(),
        'product_group_id' => $groupId,
    ]);
    $branchProduct = Product::factory()->create([
        'branch_id' => $targetBranch->id,
        'product_group_id' => $groupId,
        'name' => $mainProduct->name,
    ]);

    Batch::factory()->for($mainProduct)->withStock(30)->create([
        'branch_id' => Branch::resolveMainBranchId(),
        'purchase_price' => 80,
    ]);

    $this->actingAs($user)
        ->post('/inventory/stock-distribution', [
            'to_branch_id' => $targetBranch->id,
            'date' => now()->format('Y-m-d'),
            'comment' => 'Branch copy allocation',
            'items' => [
                ['product_id' => $mainProduct->id, 'variation_id' => null, 'quantity' => '12'],
            ],
        ])
        ->assertRedirect();

    $destinationBatch = Batch::query()
        ->where('product_id', $branchProduct->id)
        ->where('branch_id', $targetBranch->id)
        ->first();

    expect($destinationBatch)->not->toBeNull();
    expect((float) $destinationBatch->available)->toBe(12.0);

    expect(
        Batch::query()
            ->where('product_id', $mainProduct->id)
            ->where('branch_id', $targetBranch->id)
            ->exists(),
    )->toBeFalse();
});

test('stock distribution create includes ecommerce branch as destination', function () {
    $this->artisan('permissions:sync');
    $this->withoutVite();

    EcommerceBranchService::resetResolvedId();

    $ecommerceBranch = Branch::query()->firstOrCreate(
        ['name' => Branch::ECOMMERCE_BRANCH_NAME],
        Branch::factory()->make(['name' => Branch::ECOMMERCE_BRANCH_NAME])->toArray(),
    );

    $mainBranch = Branch::query()->firstOrCreate(
        ['name' => Branch::MAIN_BRANCH_NAME],
        Branch::factory()->make(['name' => Branch::MAIN_BRANCH_NAME])->toArray(),
    );

    EcommerceBranchService::resetResolvedId();

    expect($ecommerceBranch->id)->not->toBe($mainBranch->id);
    expect(Branch::resolveMainBranchId())->toBe($mainBranch->id);

    $user = superAdminUser(['inventory.stock-distribution.create']);

    $this->actingAs($user)
        ->get('/inventory/stock-distribution/create')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/inventory/stock-distribution/create')
            ->where('branches', fn ($branches) => collect($branches)->pluck('id')->contains($ecommerceBranch->id)));
});

test('super admin can distribute stock to ecommerce branch', function () {
    $this->artisan('permissions:sync');

    EcommerceBranchService::resetResolvedId();

    $ecommerceBranch = Branch::query()->firstOrCreate(
        ['name' => Branch::ECOMMERCE_BRANCH_NAME],
        Branch::factory()->make(['name' => Branch::ECOMMERCE_BRANCH_NAME])->toArray(),
    );

    Branch::query()->firstOrCreate(
        ['name' => Branch::MAIN_BRANCH_NAME],
        Branch::factory()->make(['name' => Branch::MAIN_BRANCH_NAME])->toArray(),
    );

    EcommerceBranchService::resetResolvedId();

    $mainBranchId = Branch::resolveMainBranchId();
    $user = superAdminUser(['inventory.stock-distribution.create']);
    $groupId = (string) Str::uuid();

    $mainProduct = Product::factory()->create([
        'branch_id' => $mainBranchId,
        'product_group_id' => $groupId,
    ]);
    $ecommerceProduct = Product::factory()->create([
        'branch_id' => $ecommerceBranch->id,
        'product_group_id' => $groupId,
        'name' => $mainProduct->name,
    ]);

    Batch::factory()->for($mainProduct)->withStock(15)->create([
        'branch_id' => $mainBranchId,
        'purchase_price' => 50,
    ]);

    $this->actingAs($user)
        ->post('/inventory/stock-distribution', [
            'to_branch_id' => $ecommerceBranch->id,
            'date' => now()->format('Y-m-d'),
            'comment' => 'Ecommerce allocation',
            'items' => [
                ['product_id' => $mainProduct->id, 'variation_id' => null, 'quantity' => '6'],
            ],
        ])
        ->assertRedirect();

    $destinationBatch = Batch::query()
        ->where('product_id', $ecommerceProduct->id)
        ->where('branch_id', $ecommerceBranch->id)
        ->first();

    expect($destinationBatch)->not->toBeNull();
    expect((float) $destinationBatch->available)->toBe(6.0);
});
