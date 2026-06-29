<?php

use App\Enums\ProductLogType;
use App\Enums\StockDistributionStatus;
use App\Enums\SystemAccountKey;
use App\Models\Batch;
use App\Models\Branch;
use App\Models\ChartOfAccount;
use App\Models\Ledger;
use App\Models\Product;
use App\Models\ProductInOutLog;
use App\Models\ProductVariation;
use App\Models\Purchase;
use App\Models\StockDistribution;
use App\Models\StockDistributionProduct;
use App\Models\Supplier;
use App\Models\Transaction;
use App\Models\User;
use App\Services\EcommerceBranchService;
use App\Services\SystemAccountService;
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

function branchReceiverUser(int $branchId): User
{
    Permission::findOrCreate('inventory.stock-distribution.receive', 'web');
    $user = User::factory()->create(['branch_id' => $branchId]);
    $user->givePermissionTo('inventory.stock-distribution.receive');

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

test('main branch user can access stock distribution', function () {
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

    $distributionOutBefore = ProductInOutLog::query()->where('type', ProductLogType::Distribution_Out->value)->count();
    $distributionInBefore = ProductInOutLog::query()->where('type', ProductLogType::Distribution_In->value)->count();

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
    expect($distribution->status)->toBe(StockDistributionStatus::Pending);
    expect($distribution->products)->toHaveCount(1);
    expect((float) $distribution->products->first()->quantity)->toBe(8.0);
    expect((float) $distribution->products->first()->main_stock_before)->toBe(25.0);

    $mainBatch->refresh();
    expect((float) $mainBatch->available)->toBe(17.0);

    expect(Batch::query()
        ->where('product_id', $product->id)
        ->where('branch_id', $targetBranch->id)
        ->exists())->toBeFalse();

    expect(ProductInOutLog::query()->where('type', ProductLogType::Distribution_Out->value)->count())
        ->toBeGreaterThan($distributionOutBefore);
    expect(ProductInOutLog::query()->where('type', ProductLogType::Distribution_In->value)->count())
        ->toBe($distributionInBefore);

    expect(Transaction::query()
        ->where('source_type', StockDistribution::class)
        ->where('source_id', $distribution->id)
        ->exists())->toBeFalse();

    $this->actingAs(branchReceiverUser($targetBranch->id))
        ->post("/inventory/stock-distribution/{$distribution->id}/receive")
        ->assertRedirect(route('inventory.stock-distribution.received'));

    $distribution->refresh();
    expect($distribution->status)->toBe(StockDistributionStatus::Received);
    expect($distribution->received_by_user_id)->not->toBeNull();

    $destinationProduct = Product::query()
        ->where('branch_id', $targetBranch->id)
        ->where('name', $product->name)
        ->first();

    expect($destinationProduct)->not->toBeNull('product should be created for destination branch');

    $destinationBatch = Batch::query()
        ->where('product_id', $destinationProduct->id)
        ->where('branch_id', $targetBranch->id)
        ->first();

    expect($destinationBatch)->not->toBeNull();
    expect((float) $destinationBatch->available)->toBe(8.0);

    expect(ProductInOutLog::query()->where('type', ProductLogType::Distribution_In->value)->count())
        ->toBeGreaterThan($distributionInBefore);

    expect(Transaction::query()
        ->where('source_type', StockDistributionProduct::class)
        ->whereIn('source_id', $distribution->products->pluck('id'))
        ->exists())->toBeTrue();
});

test('stock distribution posts intercompany inventory journal entries', function () {
    $this->artisan('permissions:sync');

    $mainBranchId = Branch::resolveMainBranchId();
    $user = superAdminUser(['inventory.stock-distribution.create']);
    $targetBranch = Branch::factory()->create();

    seedAccountingAccounts(branchId: $mainBranchId);
    seedAccountingAccounts(branchId: $targetBranch->id);

    $product = Product::factory()->create(['branch_id' => $mainBranchId]);
    Batch::factory()->for($product)->withStock(20)->create([
        'branch_id' => $mainBranchId,
        'purchase_price' => 100,
    ]);

    $this->actingAs($user)
        ->post('/inventory/stock-distribution', [
            'to_branch_id' => $targetBranch->id,
            'date' => now()->format('Y-m-d'),
            'comment' => 'Intercompany transfer',
            'items' => [
                [
                    'product_id' => $product->id,
                    'variation_id' => null,
                    'quantity' => '5',
                ],
            ],
        ])
        ->assertRedirect(route('inventory.stock-distribution.index'));

    $distribution = StockDistribution::query()->latest('id')->first();
    expect($distribution)->not->toBeNull();

    $this->actingAs(branchReceiverUser($targetBranch->id))
        ->post("/inventory/stock-distribution/{$distribution->id}/receive")
        ->assertRedirect(route('inventory.stock-distribution.received'));

    $line = $distribution->products()->first();
    expect($line)->not->toBeNull();
    expect($line->received_at)->not->toBeNull();

    $transaction = Transaction::query()
        ->where('source_type', StockDistributionProduct::class)
        ->where('source_id', $line->id)
        ->first();

    expect($transaction)->not->toBeNull();

    $ledgers = Ledger::query()->where('transaction_id', $transaction->id)->get();
    expect($ledgers)->toHaveCount(4);

    $expectedCost = 500.0;
    $mainInventoryId = SystemAccountService::id(SystemAccountKey::ProductInventory, $mainBranchId);
    $branchInventoryId = SystemAccountService::id(SystemAccountKey::ProductInventory, $targetBranch->id);
    $mainReceivableId = SystemAccountService::id(SystemAccountKey::IntercompanyReceivable, $mainBranchId);
    $branchPayableId = SystemAccountService::id(SystemAccountKey::IntercompanyPayable, $targetBranch->id);

    $debits = $ledgers->where('debit', '>', 0);
    $credits = $ledgers->where('credit', '>', 0);

    expect(round((float) $debits->sum('debit'), 2))->toBe(round($expectedCost * 2, 2));
    expect(round((float) $credits->sum('credit'), 2))->toBe(round($expectedCost * 2, 2));
    expect(round((float) $ledgers->sum('debit'), 2))->toBe(round((float) $ledgers->sum('credit'), 2));

    expect($debits->pluck('account_id')->all())->toEqualCanonicalizing([
        $branchInventoryId,
        $mainReceivableId,
    ]);
    expect($credits->pluck('account_id')->all())->toEqualCanonicalizing([
        $mainInventoryId,
        $branchPayableId,
    ]);

    expect(round((float) $debits->firstWhere('account_id', $branchInventoryId)?->debit, 2))->toBe($expectedCost);
    expect(round((float) $debits->firstWhere('account_id', $mainReceivableId)?->debit, 2))->toBe($expectedCost);
    expect(round((float) $credits->firstWhere('account_id', $mainInventoryId)?->credit, 2))->toBe($expectedCost);
    expect(round((float) $credits->firstWhere('account_id', $branchPayableId)?->credit, 2))->toBe($expectedCost);
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
    expect($distribution->status)->toBe(StockDistributionStatus::Pending);

    expect(Batch::query()
        ->where('product_id', $product->id)
        ->where('branch_id', $otherBranch->id)
        ->exists())->toBeFalse();

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

test('branch sends received stock to main and admin receives it', function () {
    $this->artisan('permissions:sync');

    $admin = superAdminUser([
        'inventory.stock-distribution.create',
        'inventory.stock-distribution.update',
    ]);
    $targetBranch = Branch::factory()->create();

    $product = Product::factory()->create([
        'branch_id' => Branch::resolveMainBranchId(),
        'name' => 'Two Step Return '.fake()->unique()->numerify('###'),
    ]);
    $mainBatch = Batch::factory()->for($product)->withStock(20)->create([
        'branch_id' => Branch::resolveMainBranchId(),
        'purchase_price' => 100,
    ]);

    $this->actingAs($admin)
        ->post('/inventory/stock-distribution', [
            'to_branch_id' => $targetBranch->id,
            'date' => now()->format('Y-m-d'),
            'comment' => 'Two step return test',
            'items' => [
                ['product_id' => $product->id, 'variation_id' => null, 'quantity' => '8'],
            ],
        ])
        ->assertRedirect();

    $distribution = StockDistribution::query()->latest('id')->first();
    $receiver = branchReceiverUser($targetBranch->id);

    $this->actingAs($receiver)
        ->post("/inventory/stock-distribution/{$distribution->id}/receive")
        ->assertRedirect(route('inventory.stock-distribution.received'));

    $mainBatch->refresh();
    expect((float) $mainBatch->available)->toBe(12.0);

    $branchProduct = Product::query()
        ->where('branch_id', $targetBranch->id)
        ->where('name', $product->name)
        ->firstOrFail();
    $branchStock = fn (): float => (float) Batch::query()
        ->where('product_id', $branchProduct->id)
        ->where('branch_id', $targetBranch->id)
        ->sum('available');
    expect($branchStock())->toBe(8.0);

    // Step 1 — branch sends the stock back: leaves the branch, main NOT yet restored.
    $this->actingAs($receiver)
        ->post("/inventory/stock-distribution/{$distribution->id}/send-return")
        ->assertRedirect(route('inventory.stock-distribution.received'));

    $distribution->refresh();
    expect($distribution->status)->toBe(StockDistributionStatus::ReturnPending);
    expect($distribution->return_sent_at)->not->toBeNull();
    expect($branchStock())->toBe(0.0);
    $mainBatch->refresh();
    expect((float) $mainBatch->available)->toBe(12.0);

    // Step 2 — admin receives the return: main warehouse stock restored.
    $this->actingAs($admin)
        ->post("/inventory/stock-distribution/{$distribution->id}/receive-return")
        ->assertRedirect(route('inventory.stock-distribution.index'));

    $distribution->refresh();
    expect($distribution->status)->toBe(StockDistributionStatus::Returned);
    expect($distribution->return_received_at)->not->toBeNull();
    $mainBatch->refresh();
    expect((float) $mainBatch->available)->toBe(20.0);
    expect($branchStock())->toBe(0.0);
});

test('stock distribution index exposes return status labels', function () {
    $this->artisan('permissions:sync');
    $this->withoutVite();

    $mainBranchId = Branch::resolveMainBranchId();
    $admin = superAdminUser(['inventory.stock-distribution.view']);
    $targetBranch = Branch::factory()->create();

    $returnPending = StockDistribution::query()->create([
        'branch_id' => $mainBranchId,
        'from_branch_id' => $mainBranchId,
        'to_branch_id' => $targetBranch->id,
        'date' => now(),
        'status' => StockDistributionStatus::ReturnPending,
        'comment' => 'Return pending list status '.fake()->unique()->numerify('###'),
    ]);

    $returned = StockDistribution::query()->create([
        'branch_id' => $mainBranchId,
        'from_branch_id' => $mainBranchId,
        'to_branch_id' => $targetBranch->id,
        'date' => now(),
        'status' => StockDistributionStatus::Returned,
        'comment' => 'Returned list status '.fake()->unique()->numerify('###'),
    ]);

    $props = $this->actingAs($admin)
        ->get('/inventory/stock-distribution')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/inventory/stock-distribution/index')
            ->has('distributions.data'))
        ->original
        ->getData()['page']['props'];

    $rows = collect($props['distributions']['data']);

    expect($rows->firstWhere('id', $returnPending->id)['status_label'])->toBe('Return Pending');
    expect($rows->firstWhere('id', $returned->id)['status_label'])->toBe('Returned');
});

test('two-step return reverses all distribution accounting only at admin receive', function () {
    $this->artisan('permissions:sync');

    $mainBranchId = Branch::resolveMainBranchId();
    $admin = superAdminUser([
        'inventory.stock-distribution.create',
        'inventory.stock-distribution.update',
    ]);
    $targetBranch = Branch::factory()->create();

    seedAccountingAccounts(branchId: $mainBranchId);
    seedAccountingAccounts(branchId: $targetBranch->id);

    $product = Product::factory()->create([
        'branch_id' => $mainBranchId,
        'name' => 'Acct Reverse '.fake()->unique()->numerify('###'),
    ]);
    Batch::factory()->for($product)->withStock(10)->create([
        'branch_id' => $mainBranchId,
        'purchase_price' => 100,
    ]);

    $mainInventoryId = SystemAccountService::id(SystemAccountKey::ProductInventory, $mainBranchId);
    $branchInventoryId = SystemAccountService::id(SystemAccountKey::ProductInventory, $targetBranch->id);
    $mainReceivableId = SystemAccountService::id(SystemAccountKey::IntercompanyReceivable, $mainBranchId);
    $branchPayableId = SystemAccountService::id(SystemAccountKey::IntercompanyPayable, $targetBranch->id);

    $balance = fn (int $id): float => (float) ChartOfAccount::query()->whereKey($id)->value('current_balance');

    $mainInvBefore = $balance($mainInventoryId);
    $branchInvBefore = $balance($branchInventoryId);
    $mainRecvBefore = $balance($mainReceivableId);
    $branchPayBefore = $balance($branchPayableId);

    $this->actingAs($admin)
        ->post('/inventory/stock-distribution', [
            'to_branch_id' => $targetBranch->id,
            'date' => now()->format('Y-m-d'),
            'comment' => 'Accounting reversal test',
            'items' => [
                ['product_id' => $product->id, 'variation_id' => null, 'quantity' => '5'],
            ],
        ])
        ->assertRedirect();

    $distribution = StockDistribution::query()->latest('id')->first();
    $lineIds = $distribution->products->pluck('id');
    $receiver = branchReceiverUser($targetBranch->id);

    $this->actingAs($receiver)
        ->post("/inventory/stock-distribution/{$distribution->id}/receive")
        ->assertRedirect(route('inventory.stock-distribution.received'));

    // After receive: per-line intercompany journals exist and balances moved by cost (5 * 100).
    expect(Transaction::query()
        ->where('source_type', StockDistributionProduct::class)
        ->whereIn('source_id', $lineIds)
        ->exists())->toBeTrue();

    expect($balance($branchInventoryId))->toBe($branchInvBefore + 500.0);
    expect($balance($mainInventoryId))->toBe($mainInvBefore - 500.0);
    expect($balance($mainReceivableId))->toBe($mainRecvBefore + 500.0);
    expect($balance($branchPayableId))->toBe($branchPayBefore + 500.0);

    // Step 1 — branch sends: NO accounting change yet (books stay balanced in place).
    $this->actingAs($receiver)
        ->post("/inventory/stock-distribution/{$distribution->id}/send-return")
        ->assertRedirect(route('inventory.stock-distribution.received'));

    expect(Transaction::query()
        ->where('source_type', StockDistributionProduct::class)
        ->whereIn('source_id', $lineIds)
        ->exists())->toBeTrue();
    expect($balance($branchInventoryId))->toBe($branchInvBefore + 500.0);
    expect($balance($mainInventoryId))->toBe($mainInvBefore - 500.0);
    expect($balance($mainReceivableId))->toBe($mainRecvBefore + 500.0);
    expect($balance($branchPayableId))->toBe($branchPayBefore + 500.0);

    // Step 2 — admin receives: every posted journal reverses and all balances restore.
    $this->actingAs($admin)
        ->post("/inventory/stock-distribution/{$distribution->id}/receive-return")
        ->assertRedirect(route('inventory.stock-distribution.index'));

    expect(Transaction::query()
        ->where('source_type', StockDistributionProduct::class)
        ->whereIn('source_id', $lineIds)
        ->exists())->toBeFalse();

    expect($balance($mainInventoryId))->toBe($mainInvBefore);
    expect($balance($branchInventoryId))->toBe($branchInvBefore);
    expect($balance($mainReceivableId))->toBe($mainRecvBefore);
    expect($balance($branchPayableId))->toBe($branchPayBefore);
});

test('branch cannot send a return when the stock was already sold', function () {
    $this->artisan('permissions:sync');

    $mainBranchId = Branch::resolveMainBranchId();
    $admin = superAdminUser(['inventory.stock-distribution.create']);
    $targetBranch = Branch::factory()->create();

    seedAccountingAccounts(branchId: $mainBranchId);
    seedAccountingAccounts(branchId: $targetBranch->id);

    $product = Product::factory()->create([
        'branch_id' => $mainBranchId,
        'name' => 'Sold Then Return '.fake()->unique()->numerify('###'),
    ]);
    Batch::factory()->for($product)->withStock(10)->create([
        'branch_id' => $mainBranchId,
        'purchase_price' => 100,
    ]);

    $this->actingAs($admin)
        ->post('/inventory/stock-distribution', [
            'to_branch_id' => $targetBranch->id,
            'date' => now()->format('Y-m-d'),
            'comment' => 'Sold then return test',
            'items' => [
                ['product_id' => $product->id, 'variation_id' => null, 'quantity' => '5'],
            ],
        ])
        ->assertRedirect();

    $distribution = StockDistribution::query()->latest('id')->first();
    $receiver = branchReceiverUser($targetBranch->id);

    $this->actingAs($receiver)
        ->post("/inventory/stock-distribution/{$distribution->id}/receive")
        ->assertRedirect(route('inventory.stock-distribution.received'));

    // Simulate the branch selling the received stock by draining its batch.
    $branchProduct = Product::query()
        ->where('branch_id', $targetBranch->id)
        ->where('name', $product->name)
        ->firstOrFail();
    Batch::query()
        ->where('product_id', $branchProduct->id)
        ->where('branch_id', $targetBranch->id)
        ->update(['available' => 0]);

    $this->actingAs($receiver)
        ->post("/inventory/stock-distribution/{$distribution->id}/send-return")
        ->assertSessionHas('error');

    // Status and accounting remain intact (atomic rollback).
    $distribution->refresh();
    expect($distribution->status)->toBe(StockDistributionStatus::Received);
    expect(Transaction::query()
        ->where('source_type', StockDistributionProduct::class)
        ->whereIn('source_id', $distribution->products->pluck('id'))
        ->exists())->toBeTrue();
});

test('branch cannot send a pending distribution to main', function () {
    $this->artisan('permissions:sync');

    $user = superAdminUser(['inventory.stock-distribution.create']);
    $targetBranch = Branch::factory()->create();

    $product = Product::factory()->create([
        'branch_id' => Branch::resolveMainBranchId(),
        'name' => 'Pending Return '.fake()->unique()->numerify('###'),
    ]);
    Batch::factory()->for($product)->withStock(20)->create([
        'branch_id' => Branch::resolveMainBranchId(),
        'purchase_price' => 100,
    ]);

    $this->actingAs($user)
        ->post('/inventory/stock-distribution', [
            'to_branch_id' => $targetBranch->id,
            'date' => now()->format('Y-m-d'),
            'comment' => 'Pending return test',
            'items' => [
                ['product_id' => $product->id, 'variation_id' => null, 'quantity' => '4'],
            ],
        ])
        ->assertRedirect();

    $distribution = StockDistribution::query()->latest('id')->first();

    $this->actingAs(branchReceiverUser($targetBranch->id))
        ->post("/inventory/stock-distribution/{$distribution->id}/send-return")
        ->assertStatus(422);

    $distribution->refresh();
    expect($distribution->status)->toBe(StockDistributionStatus::Pending);
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

    $distribution = StockDistribution::query()->latest('id')->first();

    $this->actingAs(branchReceiverUser($targetBranch->id))
        ->post("/inventory/stock-distribution/{$distribution->id}/receive")
        ->assertRedirect(route('inventory.stock-distribution.received'));

    $destinationProduct = Product::query()
        ->where('branch_id', $targetBranch->id)
        ->where('name', $product->name)
        ->first();

    expect($destinationProduct)->not->toBeNull('product should be created for destination branch');

    $destinationBatch = Batch::query()
        ->where('product_id', $destinationProduct->id)
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

    $distribution = StockDistribution::query()->latest('id')->first();

    $this->actingAs(branchReceiverUser($targetBranch->id))
        ->post("/inventory/stock-distribution/{$distribution->id}/receive")
        ->assertRedirect(route('inventory.stock-distribution.received'));

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
    Permission::findOrCreate('inventory.stock-distribution.receive', 'web');
    Permission::findOrCreate('inventory.purchase.view', 'web');
    Permission::findOrCreate('inventory.sell.view', 'web');
    $branchUser->givePermissionTo([
        'inventory.stock-distribution.receive',
        'inventory.purchase.view',
        'inventory.sell.view',
    ]);

    $adminPurchases = collect(app(AdminNavigation::class)->build($admin))
        ->firstWhere('title', 'Purchases');

    $branchSales = collect(app(AdminNavigation::class)->build($branchUser))
        ->firstWhere('title', 'Sales');

    $adminChildren = collect($adminPurchases['children'] ?? [])->pluck('title');
    $branchChildren = collect($branchSales['children'] ?? [])->pluck('title');

    expect($adminChildren)->toContain('Distribute Stock');
    expect($branchChildren)->not->toContain('Distribute Stock');
    expect($branchChildren)->toContain('Received Stock');
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

    $distribution = StockDistribution::query()->latest('id')->first();

    $this->actingAs(branchReceiverUser($targetBranch->id))
        ->post("/inventory/stock-distribution/{$distribution->id}/receive")
        ->assertRedirect(route('inventory.stock-distribution.received'));

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

    $user = superAdminUser(['inventory.stock-distribution.create']);

    $this->actingAs($user)
        ->get('/inventory/stock-distribution/create')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/inventory/stock-distribution/create')
            ->where('branches', fn ($branches) => collect($branches)->pluck('id')->contains($ecommerceBranch->id)));
});

test('branch user can view received stock index', function () {
    $this->artisan('permissions:sync');
    $this->withoutVite();

    $targetBranch = Branch::factory()->create();
    $user = branchReceiverUser($targetBranch->id);

    $this->actingAs($user)
        ->get('/inventory/stock-distribution/received')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/inventory/stock-distribution/index')
            ->where('isReceiverView', true));
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

    $response = $this->actingAs($user)
        ->post('/inventory/stock-distribution', [
            'to_branch_id' => $ecommerceBranch->id,
            'date' => now()->format('Y-m-d'),
            'comment' => 'Ecommerce allocation',
            'items' => [
                ['product_id' => $mainProduct->id, 'variation_id' => null, 'quantity' => '6'],
            ],
        ]);

    $response->assertRedirect(route('inventory.stock-distribution.index'))
        ->assertSessionHasNoErrors();

    $distribution = StockDistribution::query()->latest('id')->first();

    $this->actingAs(branchReceiverUser($ecommerceBranch->id))
        ->post("/inventory/stock-distribution/{$distribution->id}/receive")
        ->assertRedirect(route('inventory.stock-distribution.received'));

    $destinationBatch = Batch::query()
        ->where('product_id', $ecommerceProduct->id)
        ->where('branch_id', $ecommerceBranch->id)
        ->first();

    expect($destinationBatch)->not->toBeNull();
    expect((float) $destinationBatch->available)->toBe(6.0);
});

test('purchase can create pending stock distribution for branch', function () {
    $this->artisan('permissions:sync');

    $mainBranchId = Branch::resolveMainBranchId();
    $user = mainBranchUser(['inventory.purchase.create', 'inventory.stock-distribution.create']);
    $targetBranch = Branch::factory()->create();
    $supplier = Supplier::factory()->create(['branch_id' => $mainBranchId]);
    $product = Product::factory()->create(['branch_id' => $mainBranchId]);

    $cash = seedAccountingAccounts(branchId: $mainBranchId);

    $this->actingAs($user)
        ->post('/inventory/purchase', [
            'supplier_id' => $supplier->id,
            'date' => now()->format('Y-m-d'),
            'discount' => '0',
            'vat' => '0',
            'paid_amount' => '500',
            'payment_account_id' => $cash->id,
            'comment' => 'Purchase with distribution',
            'distribute_to_branch_id' => $targetBranch->id,
            'items' => [
                [
                    'product_id' => $product->id,
                    'variation_id' => null,
                    'unit_price' => '100',
                    'quantity' => '5',
                    'free_quantity' => '0',
                    'distribute_quantity' => '3',
                ],
            ],
        ])
        ->assertRedirect(route('inventory.purchase.index'));

    $distribution = StockDistribution::query()->latest('id')->first();

    expect($distribution)->not->toBeNull();
    expect($distribution->status)->toBe(StockDistributionStatus::Pending);
    expect($distribution->to_branch_id)->toBe($targetBranch->id);
    expect($distribution->purchase_id)->not->toBeNull();
    expect((float) $distribution->products->first()->quantity)->toBe(3.0);

    $mainBatch = Batch::query()
        ->where('product_id', $product->id)
        ->where('branch_id', $mainBranchId)
        ->first();

    expect((float) $mainBatch->available)->toBe(2.0);
});

test('purchase edit can create pending stock distribution for branch', function () {
    $this->artisan('permissions:sync');

    $mainBranchId = Branch::resolveMainBranchId();
    $user = mainBranchUser([
        'inventory.purchase.create',
        'inventory.purchase.update',
        'inventory.stock-distribution.create',
    ]);
    $targetBranch = Branch::factory()->create();
    $supplier = Supplier::factory()->create(['branch_id' => $mainBranchId]);
    $product = Product::factory()->create(['branch_id' => $mainBranchId]);

    $cash = seedAccountingAccounts(branchId: $mainBranchId);

    $this->actingAs($user)
        ->post('/inventory/purchase', [
            'supplier_id' => $supplier->id,
            'date' => now()->format('Y-m-d'),
            'discount' => '0',
            'vat' => '0',
            'paid_amount' => '500',
            'payment_account_id' => $cash->id,
            'comment' => 'Purchase without distribution',
            'items' => [
                [
                    'product_id' => $product->id,
                    'variation_id' => null,
                    'unit_price' => '100',
                    'quantity' => '5',
                    'free_quantity' => '0',
                ],
            ],
        ])
        ->assertRedirect(route('inventory.purchase.index'));

    $purchase = Purchase::query()->latest('id')->first();

    $this->actingAs($user)
        ->put("/inventory/purchase/{$purchase->id}", [
            'supplier_id' => $supplier->id,
            'date' => now()->format('Y-m-d'),
            'discount' => '0',
            'vat' => '0',
            'paid_amount' => '500',
            'payment_account_id' => $cash->id,
            'comment' => 'Purchase updated with distribution',
            'distribute_to_branch_id' => $targetBranch->id,
            'items' => [
                [
                    'product_id' => $product->id,
                    'variation_id' => null,
                    'unit_price' => '100',
                    'quantity' => '5',
                    'free_quantity' => '0',
                    'distribute_quantity' => '3',
                ],
            ],
        ])
        ->assertRedirect(route('inventory.purchase.index'));

    $distribution = StockDistribution::query()->where('purchase_id', $purchase->id)->first();

    expect($distribution)->not->toBeNull();
    expect($distribution->status)->toBe(StockDistributionStatus::Pending);
    expect($distribution->to_branch_id)->toBe($targetBranch->id);
    expect((float) $distribution->products->first()->quantity)->toBe(3.0);
});

test('purchase edit page pre-fills existing distribution branch and quantities', function () {
    $this->artisan('permissions:sync');
    $this->withoutVite();

    $mainBranchId = Branch::resolveMainBranchId();
    $user = mainBranchUser([
        'inventory.purchase.create',
        'inventory.purchase.update',
        'inventory.stock-distribution.create',
    ]);
    $targetBranch = Branch::factory()->create();
    $supplier = Supplier::factory()->create(['branch_id' => $mainBranchId]);
    $product = Product::factory()->create(['branch_id' => $mainBranchId]);

    $cash = seedAccountingAccounts(branchId: $mainBranchId);

    $this->actingAs($user)
        ->post('/inventory/purchase', [
            'supplier_id' => $supplier->id,
            'date' => now()->format('Y-m-d'),
            'discount' => '0',
            'vat' => '0',
            'paid_amount' => '500',
            'payment_account_id' => $cash->id,
            'distribute_to_branch_id' => $targetBranch->id,
            'items' => [
                [
                    'product_id' => $product->id,
                    'variation_id' => null,
                    'unit_price' => '100',
                    'quantity' => '5',
                    'free_quantity' => '0',
                    'distribute_quantity' => '3',
                ],
            ],
        ])
        ->assertRedirect(route('inventory.purchase.index'));

    $purchase = Purchase::query()->latest('id')->first();

    $this->actingAs($user)
        ->get("/inventory/purchase/{$purchase->id}/edit")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/inventory/purchase/edit')
            ->where('purchase.distribute_to_branch_id', $targetBranch->id)
            ->where('purchase.items.0.distribute_quantity', 3));
});

test('purchase edit update with existing distribution re-applies without stock error', function () {
    $this->artisan('permissions:sync');

    $mainBranchId = Branch::resolveMainBranchId();
    $user = mainBranchUser([
        'inventory.purchase.create',
        'inventory.purchase.update',
        'inventory.stock-distribution.create',
    ]);
    $targetBranch = Branch::factory()->create();
    $supplier = Supplier::factory()->create(['branch_id' => $mainBranchId]);
    $product = Product::factory()->create(['branch_id' => $mainBranchId]);

    $cash = seedAccountingAccounts(branchId: $mainBranchId);

    $this->actingAs($user)
        ->post('/inventory/purchase', [
            'supplier_id' => $supplier->id,
            'date' => now()->format('Y-m-d'),
            'discount' => '0',
            'vat' => '0',
            'paid_amount' => '500',
            'payment_account_id' => $cash->id,
            'distribute_to_branch_id' => $targetBranch->id,
            'items' => [
                [
                    'product_id' => $product->id,
                    'variation_id' => null,
                    'unit_price' => '100',
                    'quantity' => '5',
                    'free_quantity' => '0',
                    'distribute_quantity' => '3',
                ],
            ],
        ])
        ->assertRedirect(route('inventory.purchase.index'));

    $purchase = Purchase::query()->latest('id')->first();
    $distributionId = StockDistribution::query()->where('purchase_id', $purchase->id)->value('id');

    $this->actingAs($user)
        ->put("/inventory/purchase/{$purchase->id}", [
            'supplier_id' => $supplier->id,
            'date' => now()->format('Y-m-d'),
            'discount' => '0',
            'vat' => '0',
            'paid_amount' => '500',
            'payment_account_id' => $cash->id,
            'comment' => 'Updated after distribution',
            'distribute_to_branch_id' => $targetBranch->id,
            'items' => [
                [
                    'product_id' => $product->id,
                    'variation_id' => null,
                    'unit_price' => '100',
                    'quantity' => '5',
                    'free_quantity' => '0',
                    'distribute_quantity' => '3',
                ],
            ],
        ])
        ->assertRedirect(route('inventory.purchase.index'));

    $newDistribution = StockDistribution::query()->where('purchase_id', $purchase->id)->first();

    expect($newDistribution)->not->toBeNull();
    expect($newDistribution->id)->not->toBe($distributionId);
    expect($newDistribution->to_branch_id)->toBe($targetBranch->id);
    expect((float) $newDistribution->products->first()->quantity)->toBe(3.0);
});

test('purchase edit can remove distributed variation line without destination catalog entry', function () {
    $this->artisan('permissions:sync');

    $mainBranchId = Branch::resolveMainBranchId();
    $user = mainBranchUser([
        'inventory.purchase.create',
        'inventory.purchase.update',
        'inventory.stock-distribution.create',
    ]);
    $targetBranch = Branch::factory()->create();
    $supplier = Supplier::factory()->create(['branch_id' => $mainBranchId]);
    $groupId = (string) Str::uuid();

    $product = Product::factory()->create([
        'branch_id' => $mainBranchId,
        'product_group_id' => $groupId,
        'name' => 'Grouped Shirt '.fake()->unique()->numerify('###'),
    ]);

    $variations = collect(['Ash-M', 'Ash-XL', 'Blue-M'])->map(fn (string $label) => ProductVariation::query()->create([
        'product_id' => $product->id,
        'branch_id' => $mainBranchId,
        'sku' => 'SKU-'.$label.'-'.fake()->unique()->numerify('####'),
        'price' => 695,
        'purchase_price' => 595,
        'stock' => 0,
        'variation_data' => ['label' => $label],
    ]));

    $cash = seedAccountingAccounts(branchId: $mainBranchId);

    $purchaseItems = $variations->map(fn (ProductVariation $variation) => [
        'product_id' => $product->id,
        'variation_id' => $variation->id,
        'unit_price' => '595',
        'quantity' => '1',
        'free_quantity' => '0',
        'distribute_quantity' => '1',
    ])->all();

    $this->actingAs($user)
        ->post('/inventory/purchase', [
            'supplier_id' => $supplier->id,
            'date' => now()->format('Y-m-d'),
            'discount' => '0',
            'vat' => '0',
            'paid_amount' => '1785',
            'payment_account_id' => $cash->id,
            'distribute_to_branch_id' => $targetBranch->id,
            'items' => $purchaseItems,
        ])
        ->assertRedirect(route('inventory.purchase.index'));

    $purchase = Purchase::query()->latest('id')->first();

    expect(StockDistribution::query()->where('purchase_id', $purchase->id)->first()?->products)->toHaveCount(3);

    $remainingItems = $variations->take(2)->map(fn (ProductVariation $variation) => [
        'product_id' => $product->id,
        'variation_id' => $variation->id,
        'unit_price' => '595',
        'quantity' => '1',
        'free_quantity' => '0',
        'distribute_quantity' => '1',
    ])->values()->all();

    $this->actingAs($user)
        ->put("/inventory/purchase/{$purchase->id}", [
            'supplier_id' => $supplier->id,
            'date' => now()->format('Y-m-d'),
            'discount' => '0',
            'vat' => '0',
            'paid_amount' => '1190',
            'payment_account_id' => $cash->id,
            'distribute_to_branch_id' => $targetBranch->id,
            'items' => $remainingItems,
        ])
        ->assertRedirect(route('inventory.purchase.index'));

    $distribution = StockDistribution::query()->where('purchase_id', $purchase->id)->first();

    expect($distribution)->not->toBeNull();
    expect($distribution->products)->toHaveCount(2);
    expect($distribution->products->sum(fn ($line) => (float) $line->quantity))->toBe(2.0);
});

test('purchase edit page exposes distribute props when allowed', function () {
    $this->artisan('permissions:sync');
    $this->withoutVite();

    $mainBranchId = Branch::resolveMainBranchId();
    $user = mainBranchUser([
        'inventory.purchase.create',
        'inventory.purchase.update',
        'inventory.stock-distribution.create',
    ]);
    $supplier = Supplier::factory()->create(['branch_id' => $mainBranchId]);
    $product = Product::factory()->create(['branch_id' => $mainBranchId]);

    $cash = seedAccountingAccounts(branchId: $mainBranchId);

    $this->actingAs($user)
        ->post('/inventory/purchase', [
            'supplier_id' => $supplier->id,
            'date' => now()->format('Y-m-d'),
            'discount' => '0',
            'vat' => '0',
            'paid_amount' => '100',
            'payment_account_id' => $cash->id,
            'items' => [
                [
                    'product_id' => $product->id,
                    'variation_id' => null,
                    'unit_price' => '100',
                    'quantity' => '1',
                    'free_quantity' => '0',
                ],
            ],
        ])
        ->assertRedirect(route('inventory.purchase.index'));

    $purchase = Purchase::query()->latest('id')->first();

    $this->actingAs($user)
        ->get("/inventory/purchase/{$purchase->id}/edit")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/inventory/purchase/edit')
            ->where('canDistribute', true)
            ->has('branches'));
});

test('branch can partially receive selected distribution lines', function () {
    $this->artisan('permissions:sync');

    $user = superAdminUser(['inventory.stock-distribution.create']);
    $targetBranch = Branch::factory()->create();

    $productA = Product::factory()->create(['branch_id' => Branch::resolveMainBranchId(), 'name' => 'Partial A '.fake()->unique()->numerify('###')]);
    $productB = Product::factory()->create(['branch_id' => Branch::resolveMainBranchId(), 'name' => 'Partial B '.fake()->unique()->numerify('###')]);

    Batch::factory()->for($productA)->withStock(10)->create([
        'branch_id' => Branch::resolveMainBranchId(),
        'purchase_price' => 50,
    ]);
    Batch::factory()->for($productB)->withStock(10)->create([
        'branch_id' => Branch::resolveMainBranchId(),
        'purchase_price' => 60,
    ]);

    $this->actingAs($user)
        ->post('/inventory/stock-distribution', [
            'to_branch_id' => $targetBranch->id,
            'date' => now()->format('Y-m-d'),
            'comment' => 'Partial receive test',
            'items' => [
                ['product_id' => $productA->id, 'variation_id' => null, 'quantity' => '4'],
                ['product_id' => $productB->id, 'variation_id' => null, 'quantity' => '6'],
            ],
        ])
        ->assertRedirect();

    $distribution = StockDistribution::query()->latest('id')->first();
    $lines = $distribution->products()->orderBy('id')->get();
    expect($lines)->toHaveCount(2);

    $receiver = branchReceiverUser($targetBranch->id);

    $this->actingAs($receiver)
        ->post("/inventory/stock-distribution/{$distribution->id}/receive", [
            'line_ids' => [$lines->first()->id],
        ])
        ->assertRedirect(route('inventory.stock-distribution.received'));

    $distribution->refresh();
    $lines = $distribution->products()->orderBy('id')->get();

    expect($distribution->status)->toBe(StockDistributionStatus::PartiallyReceived);
    expect($lines->first()->received_at)->not->toBeNull();
    expect($lines->last()->received_at)->toBeNull();

    $branchProductA = Product::query()
        ->where('branch_id', $targetBranch->id)
        ->where('name', $productA->name)
        ->first();

    expect($branchProductA)->not->toBeNull('product A should be created for destination branch');

    expect(Batch::query()
        ->where('product_id', $branchProductA->id)
        ->where('branch_id', $targetBranch->id)
        ->exists())->toBeTrue();
    expect(Batch::query()
        ->where('product_id', $productB->id)
        ->where('branch_id', $targetBranch->id)
        ->exists())->toBeFalse();

    $this->actingAs($receiver)
        ->post("/inventory/stock-distribution/{$distribution->id}/receive")
        ->assertRedirect(route('inventory.stock-distribution.received'));

    $distribution->refresh();
    expect($distribution->status)->toBe(StockDistributionStatus::Received);
    expect($distribution->products()->whereNull('received_at')->count())->toBe(0);
});

test('receiving distribution auto-creates product for branch when no sibling exists', function () {
    $this->artisan('permissions:sync');

    $mainBranchId = Branch::resolveMainBranchId();
    $user = superAdminUser(['inventory.stock-distribution.create']);
    $targetBranch = Branch::factory()->create();
    $groupId = (string) Str::uuid();

    $mainProduct = Product::factory()->create([
        'branch_id' => $mainBranchId,
        'product_group_id' => $groupId,
        'name' => 'Auto Replicate Product '.fake()->unique()->numerify('###'),
        'purchase_price' => 100,
        'sale_price' => 150,
    ]);

    Batch::factory()->for($mainProduct)->withStock(20)->create([
        'branch_id' => $mainBranchId,
        'purchase_price' => 100,
    ]);

    $this->actingAs($user)
        ->post('/inventory/stock-distribution', [
            'to_branch_id' => $targetBranch->id,
            'date' => now()->format('Y-m-d'),
            'comment' => null,
            'items' => [
                ['product_id' => $mainProduct->id, 'variation_id' => null, 'quantity' => '5'],
            ],
        ])
        ->assertRedirect();

    $distribution = StockDistribution::query()->latest('id')->first();

    expect(Product::query()->where('product_group_id', $groupId)->where('branch_id', $targetBranch->id)->exists())
        ->toBeFalse('branch product should not exist before receive');

    $this->actingAs(branchReceiverUser($targetBranch->id))
        ->post("/inventory/stock-distribution/{$distribution->id}/receive")
        ->assertRedirect(route('inventory.stock-distribution.received'));

    $branchProduct = Product::query()
        ->where('product_group_id', $groupId)
        ->where('branch_id', $targetBranch->id)
        ->first();

    expect($branchProduct)->not->toBeNull('product should be auto-created for destination branch');
    expect($branchProduct->name)->toBe($mainProduct->name);

    $destinationBatch = Batch::query()
        ->where('product_id', $branchProduct->id)
        ->where('branch_id', $targetBranch->id)
        ->first();

    expect($destinationBatch)->not->toBeNull();
    expect((float) $destinationBatch->available)->toBe(5.0);
});

test('receiving distribution auto-creates product for branch when variation has no sibling', function () {
    $this->artisan('permissions:sync');

    $mainBranchId = Branch::resolveMainBranchId();
    $user = superAdminUser(['inventory.stock-distribution.create']);
    $targetBranch = Branch::factory()->create();
    $groupId = (string) Str::uuid();

    $mainProduct = Product::factory()->create([
        'branch_id' => $mainBranchId,
        'product_group_id' => $groupId,
        'name' => 'Auto Replicate Variant Product '.fake()->unique()->numerify('###'),
        'purchase_price' => 200,
        'sale_price' => 250,
    ]);

    $variation = ProductVariation::query()->create([
        'product_id' => $mainProduct->id,
        'branch_id' => $mainBranchId,
        'sku' => 'SKU-AUTOREPL-'.fake()->unique()->numerify('####'),
        'price' => 250,
        'purchase_price' => 200,
        'stock' => 10,
        'variation_data' => ['label' => 'Red-L'],
    ]);

    $this->actingAs($user)
        ->post('/inventory/stock-distribution', [
            'to_branch_id' => $targetBranch->id,
            'date' => now()->format('Y-m-d'),
            'comment' => null,
            'items' => [
                ['product_id' => $mainProduct->id, 'variation_id' => $variation->id, 'quantity' => '3'],
            ],
        ])
        ->assertRedirect();

    $distribution = StockDistribution::query()->latest('id')->first();

    expect(Product::query()->where('product_group_id', $groupId)->where('branch_id', $targetBranch->id)->exists())
        ->toBeFalse('branch product should not exist before receive');

    $this->actingAs(branchReceiverUser($targetBranch->id))
        ->post("/inventory/stock-distribution/{$distribution->id}/receive")
        ->assertRedirect(route('inventory.stock-distribution.received'));

    $branchProduct = Product::query()
        ->where('product_group_id', $groupId)
        ->where('branch_id', $targetBranch->id)
        ->first();

    expect($branchProduct)->not->toBeNull('product should be auto-created for destination branch');

    $branchVariation = ProductVariation::query()
        ->where('product_id', $branchProduct->id)
        ->where('branch_id', $targetBranch->id)
        ->where('sku', $variation->sku)
        ->first();

    expect($branchVariation)->not->toBeNull('variation should be created for destination branch');
    expect((float) $branchVariation->stock)->toBe(3.0);
});

test('receiving distribution auto-creates product for branch when source product has no group', function () {
    $this->artisan('permissions:sync');

    $mainBranchId = Branch::resolveMainBranchId();
    $user = superAdminUser(['inventory.stock-distribution.create']);
    $targetBranch = Branch::factory()->create();

    $mainProduct = Product::factory()->create([
        'branch_id' => $mainBranchId,
        'product_group_id' => null,
        'name' => 'Standalone Group Product '.fake()->unique()->numerify('###'),
        'purchase_price' => 100,
        'sale_price' => 150,
    ]);

    Batch::factory()->for($mainProduct)->withStock(20)->create([
        'branch_id' => $mainBranchId,
        'purchase_price' => 100,
    ]);

    expect($mainProduct->product_group_id)->toBeNull();

    $this->actingAs($user)
        ->post('/inventory/stock-distribution', [
            'to_branch_id' => $targetBranch->id,
            'date' => now()->format('Y-m-d'),
            'comment' => null,
            'items' => [
                ['product_id' => $mainProduct->id, 'variation_id' => null, 'quantity' => '5'],
            ],
        ])
        ->assertRedirect();

    $distribution = StockDistribution::query()->latest('id')->first();

    $this->actingAs(branchReceiverUser($targetBranch->id))
        ->post("/inventory/stock-distribution/{$distribution->id}/receive")
        ->assertRedirect(route('inventory.stock-distribution.received'));

    $mainProduct->refresh();

    expect($mainProduct->product_group_id)->not->toBeNull('source product should be assigned a group when replicated');

    $branchProduct = Product::query()
        ->where('product_group_id', $mainProduct->product_group_id)
        ->where('branch_id', $targetBranch->id)
        ->first();

    expect($branchProduct)->not->toBeNull('product should be auto-created for destination branch');
    expect($branchProduct->name)->toBe($mainProduct->name);

    $destinationBatch = Batch::query()
        ->where('product_id', $branchProduct->id)
        ->where('branch_id', $targetBranch->id)
        ->first();

    expect($destinationBatch)->not->toBeNull();
    expect((float) $destinationBatch->available)->toBe(5.0);
});

test('receiving distribution auto-creates variant product for branch when source has no group', function () {
    $this->artisan('permissions:sync');

    $mainBranchId = Branch::resolveMainBranchId();
    $user = superAdminUser(['inventory.stock-distribution.create']);
    $targetBranch = Branch::factory()->create();

    $mainProduct = Product::factory()->create([
        'branch_id' => $mainBranchId,
        'product_group_id' => null,
        'name' => 'Standalone Variant Product '.fake()->unique()->numerify('###'),
        'purchase_price' => 200,
        'sale_price' => 250,
    ]);

    $variation = ProductVariation::query()->create([
        'product_id' => $mainProduct->id,
        'branch_id' => $mainBranchId,
        'sku' => 'SKU-STANDVAR-'.fake()->unique()->numerify('####'),
        'price' => 250,
        'purchase_price' => 200,
        'stock' => 10,
        'variation_data' => ['label' => 'Red-L', 'Color' => 'Red', 'Size' => 'L'],
    ]);

    expect($mainProduct->product_group_id)->toBeNull();

    $this->actingAs($user)
        ->post('/inventory/stock-distribution', [
            'to_branch_id' => $targetBranch->id,
            'date' => now()->format('Y-m-d'),
            'comment' => null,
            'items' => [
                ['product_id' => $mainProduct->id, 'variation_id' => $variation->id, 'quantity' => '3'],
            ],
        ])
        ->assertRedirect();

    $distribution = StockDistribution::query()->latest('id')->first();

    $this->actingAs(branchReceiverUser($targetBranch->id))
        ->post("/inventory/stock-distribution/{$distribution->id}/receive")
        ->assertRedirect(route('inventory.stock-distribution.received'));

    $mainProduct->refresh();

    expect($mainProduct->product_group_id)->not->toBeNull('source product should be assigned a group when replicated');

    $branchProduct = Product::query()
        ->where('product_group_id', $mainProduct->product_group_id)
        ->where('branch_id', $targetBranch->id)
        ->first();

    expect($branchProduct)->not->toBeNull('product should be auto-created for destination branch');
    expect($branchProduct->name)->toBe($mainProduct->name);

    $branchVariation = ProductVariation::query()
        ->where('product_id', $branchProduct->id)
        ->where('branch_id', $targetBranch->id)
        ->where('sku', $variation->sku)
        ->first();

    expect($branchVariation)->not->toBeNull('variation should be created for destination branch');
    expect((float) $branchVariation->stock)->toBe(3.0);
    expect($branchVariation->variation_data['Color'])->toBe('Red');
    expect($branchVariation->variation_data['Size'])->toBe('L');
});
