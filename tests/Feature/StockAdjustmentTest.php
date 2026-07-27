<?php

use App\Enums\ProductLogType;
use App\Enums\StockAdjustmentType;
use App\Enums\SystemAccountKey;
use App\Models\Batch;
use App\Models\Branch;
use App\Models\Ledger;
use App\Models\Product;
use App\Models\ProductVariation;
use App\Models\StockAdjustment;
use App\Models\Supplier;
use App\Models\Transaction;
use App\Models\User;
use App\Services\SystemAccountService;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Facades\Artisan;

function stockAdjustmentBranch(): Branch
{
    return Branch::factory()->create();
}

function stockAdjustmentUser(Branch $branch): User
{
    Artisan::call('permissions:sync');

    $user = User::factory()->create(['branch_id' => $branch->id]);
    $user->givePermissionTo([
        'inventory.stock-adjustment.view',
        'inventory.stock-adjustment.create',
        'inventory.stock-adjustment.delete',
    ]);

    return $user;
}

test('guests are redirected from stock adjustment', function () {
    $this->get('/inventory/stock-adjustment')->assertRedirect(route('login'));
});

test('stock adjustment index requires permission', function () {
    $branch = stockAdjustmentBranch();
    $user = User::factory()->create(['branch_id' => $branch->id]);

    $this->actingAs($user)
        ->get(route('inventory.stock-adjustment.index'))
        ->assertForbidden();
});

test('stock adjustment decrease reduces batch stock', function () {
    $this->withoutMiddleware(PreventRequestForgery::class);

    $branch = stockAdjustmentBranch();
    $user = stockAdjustmentUser($branch);
    seedAccountingAccounts(branchId: $branch->id);

    $product = Product::factory()->create([
        'branch_id' => $branch->id,
        'purchase_price' => 50,
        'sale_price' => 100,
        'status' => 1,
    ]);

    $batch = Batch::factory()->create([
        'branch_id' => $branch->id,
        'product_id' => $product->id,
        'purchase_price' => 50,
        'available' => 10,
    ]);

    $this->actingAs($user)
        ->post(route('inventory.stock-adjustment.store'), [
            'date' => now()->format('Y-m-d'),
            'type' => StockAdjustmentType::Decrease->value,
            'comment' => 'Count correction',
            'items' => [
                [
                    'product_id' => $product->id,
                    'variation_id' => null,
                    'quantity' => 3,
                ],
            ],
        ])
        ->assertRedirect(route('inventory.stock-adjustment.index'));

    expect((float) $batch->fresh()->available)->toBe(7.0);
    $this->assertDatabaseHas('stock_adjustments', [
        'type' => StockAdjustmentType::Decrease->value,
        'comment' => 'Count correction',
    ]);
    $this->assertDatabaseHas('product_in_out_logs', [
        'product_id' => $product->id,
        'type' => ProductLogType::Adjustment_Out->value,
        'quantity' => 3,
    ]);
});

test('stock adjustment increase adds batch stock', function () {
    $this->withoutMiddleware(PreventRequestForgery::class);

    $branch = stockAdjustmentBranch();
    $user = stockAdjustmentUser($branch);
    seedAccountingAccounts(branchId: $branch->id);

    $product = Product::factory()->create([
        'branch_id' => $branch->id,
        'purchase_price' => 40,
        'sale_price' => 80,
        'status' => 1,
    ]);

    $batch = Batch::factory()->create([
        'branch_id' => $branch->id,
        'product_id' => $product->id,
        'purchase_price' => 40,
        'available' => 5,
    ]);

    $this->actingAs($user)
        ->post(route('inventory.stock-adjustment.store'), [
            'date' => now()->format('Y-m-d'),
            'type' => StockAdjustmentType::Increase->value,
            'comment' => 'Found stock',
            'items' => [
                [
                    'product_id' => $product->id,
                    'variation_id' => null,
                    'quantity' => 2,
                ],
            ],
        ])
        ->assertRedirect(route('inventory.stock-adjustment.index'));

    expect((float) $batch->fresh()->available)->toBe(7.0);
    $this->assertDatabaseHas('product_in_out_logs', [
        'product_id' => $product->id,
        'type' => ProductLogType::Adjustment_In->value,
        'quantity' => 2,
    ]);
});

test('stock adjustment increase debits product inventory and credits stock adjustment gain', function () {
    $this->withoutMiddleware(PreventRequestForgery::class);

    $branch = stockAdjustmentBranch();
    $user = stockAdjustmentUser($branch);
    seedAccountingAccounts(branchId: $branch->id);

    $inventory = SystemAccountService::resolve(SystemAccountKey::ProductInventory, $branch->id);
    $gain = SystemAccountService::resolve(SystemAccountKey::StockAdjustmentGain, $branch->id);
    $loss = SystemAccountService::resolve(SystemAccountKey::StockAdjustmentLoss, $branch->id);
    $payables = SystemAccountService::resolve(SystemAccountKey::SupplierPayables, $branch->id);
    $supplier = Supplier::factory()->create(['branch_id' => $branch->id, 'balance' => 0]);

    $initialInventory = (float) $inventory->fresh()->current_balance;
    $initialGain = (float) $gain->fresh()->current_balance;
    $initialLoss = (float) $loss->fresh()->current_balance;
    $initialPayables = (float) $payables->fresh()->current_balance;

    $product = Product::factory()->create([
        'branch_id' => $branch->id,
        'initial_stock_supplier_id' => $supplier->id,
        'purchase_price' => 100,
        'sale_price' => 200,
        'status' => 1,
    ]);

    Batch::factory()->create([
        'branch_id' => $branch->id,
        'product_id' => $product->id,
        'purchase_price' => 100,
        'available' => 8,
    ]);

    $this->actingAs($user)
        ->post(route('inventory.stock-adjustment.store'), [
            'date' => now()->format('Y-m-d'),
            'type' => StockAdjustmentType::Increase->value,
            'items' => [
                ['product_id' => $product->id, 'variation_id' => null, 'quantity' => 2],
            ],
        ])
        ->assertRedirect(route('inventory.stock-adjustment.index'));

    expect((float) $inventory->fresh()->current_balance)->toBe(round($initialInventory + 200, 2))
        ->and((float) $gain->fresh()->current_balance)->toBe(round($initialGain + 200, 2))
        ->and((float) $loss->fresh()->current_balance)->toBe($initialLoss)
        ->and((float) $payables->fresh()->current_balance)->toBe($initialPayables)
        ->and((float) $supplier->fresh()->balance)->toBe(0.0);

    $adjustment = StockAdjustment::query()->latest('id')->firstOrFail();
    $transaction = Transaction::query()
        ->where('source_type', StockAdjustment::class)
        ->where('source_id', $adjustment->id)
        ->first();

    expect($transaction)->not->toBeNull();

    $accountIds = Ledger::query()->where('transaction_id', $transaction->id)->pluck('account_id')->all();

    expect($accountIds)->toContain($inventory->id, $gain->id);
});

test('stock adjustment decrease debits stock adjustment loss and credits product inventory', function () {
    $this->withoutMiddleware(PreventRequestForgery::class);

    $branch = stockAdjustmentBranch();
    $user = stockAdjustmentUser($branch);
    seedAccountingAccounts(branchId: $branch->id);

    $inventory = SystemAccountService::resolve(SystemAccountKey::ProductInventory, $branch->id);
    $gain = SystemAccountService::resolve(SystemAccountKey::StockAdjustmentGain, $branch->id);
    $loss = SystemAccountService::resolve(SystemAccountKey::StockAdjustmentLoss, $branch->id);

    $initialInventory = (float) $inventory->fresh()->current_balance;
    $initialGain = (float) $gain->fresh()->current_balance;
    $initialLoss = (float) $loss->fresh()->current_balance;

    $product = Product::factory()->create([
        'branch_id' => $branch->id,
        'purchase_price' => 100,
        'sale_price' => 200,
        'status' => 1,
    ]);

    Batch::factory()->create([
        'branch_id' => $branch->id,
        'product_id' => $product->id,
        'purchase_price' => 100,
        'available' => 10,
    ]);

    $this->actingAs($user)
        ->post(route('inventory.stock-adjustment.store'), [
            'date' => now()->format('Y-m-d'),
            'type' => StockAdjustmentType::Decrease->value,
            'items' => [
                ['product_id' => $product->id, 'variation_id' => null, 'quantity' => 2],
            ],
        ])
        ->assertRedirect(route('inventory.stock-adjustment.index'));

    expect((float) $inventory->fresh()->current_balance)->toBe(round($initialInventory - 200, 2))
        ->and((float) $loss->fresh()->current_balance)->toBe(round($initialLoss + 200, 2))
        ->and((float) $gain->fresh()->current_balance)->toBe($initialGain);

    $adjustment = StockAdjustment::query()->latest('id')->firstOrFail();
    $transaction = Transaction::query()
        ->where('source_type', StockAdjustment::class)
        ->where('source_id', $adjustment->id)
        ->first();

    expect($transaction)->not->toBeNull();

    $accountIds = Ledger::query()->where('transaction_id', $transaction->id)->pluck('account_id')->all();

    expect($accountIds)->toContain($inventory->id, $loss->id);
});

test('stock adjustment decrease requires variation for variant products', function () {
    $this->withoutMiddleware(PreventRequestForgery::class);

    $branch = stockAdjustmentBranch();
    $user = stockAdjustmentUser($branch);
    seedAccountingAccounts(branchId: $branch->id);

    $product = Product::factory()->create([
        'branch_id' => $branch->id,
        'purchase_price' => 80,
        'sale_price' => 150,
        'status' => 1,
    ]);

    $variation = ProductVariation::query()->create([
        'branch_id' => $branch->id,
        'product_id' => $product->id,
        'sku' => fake()->unique()->numerify('########'),
        'variation_data' => ['label' => 'M', 'Size' => 'M'],
        'price' => 150,
        'purchase_price' => 80,
        'stock' => 6,
        'status' => 1,
    ]);

    $this->actingAs($user)
        ->from(route('inventory.stock-adjustment.create'))
        ->post(route('inventory.stock-adjustment.store'), [
            'date' => now()->format('Y-m-d'),
            'type' => StockAdjustmentType::Decrease->value,
            'items' => [
                ['product_id' => $product->id, 'variation_id' => null, 'quantity' => 1],
            ],
        ])
        ->assertRedirect(route('inventory.stock-adjustment.create'))
        ->assertSessionHasErrors('items');

    expect((float) $variation->fresh()->stock)->toBe(6.0);
});

test('stock adjustment decrease updates variation stock when variation is selected', function () {
    $this->withoutMiddleware(PreventRequestForgery::class);

    $branch = stockAdjustmentBranch();
    $user = stockAdjustmentUser($branch);
    seedAccountingAccounts(branchId: $branch->id);

    $product = Product::factory()->create([
        'branch_id' => $branch->id,
        'purchase_price' => 80,
        'sale_price' => 150,
        'status' => 1,
    ]);

    $variation = ProductVariation::query()->create([
        'branch_id' => $branch->id,
        'product_id' => $product->id,
        'sku' => fake()->unique()->numerify('########'),
        'variation_data' => ['label' => 'L', 'Size' => 'L'],
        'price' => 150,
        'purchase_price' => 80,
        'stock' => 6,
        'status' => 1,
    ]);

    $this->actingAs($user)
        ->post(route('inventory.stock-adjustment.store'), [
            'date' => now()->format('Y-m-d'),
            'type' => StockAdjustmentType::Decrease->value,
            'items' => [
                ['product_id' => $product->id, 'variation_id' => $variation->id, 'quantity' => 2],
            ],
        ])
        ->assertRedirect(route('inventory.stock-adjustment.index'));

    expect((float) $variation->fresh()->stock)->toBe(4.0);
});
