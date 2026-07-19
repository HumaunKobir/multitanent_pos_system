<?php

use App\Enums\SystemAccountKey;
use App\Models\Batch;
use App\Models\Branch;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Damage;
use App\Models\DamageProduct;
use App\Models\Product;
use App\Models\ProductInitialStock;
use App\Models\ProductInOutLog;
use App\Models\Sell;
use App\Models\Supplier;
use App\Models\Transaction;
use App\Models\Unit;
use App\Models\User;
use App\Services\SystemAccountService;
use Spatie\Permission\Models\Permission;

function productDeleteAdmin(): User
{
    Permission::findOrCreate('product.delete', 'web');
    Permission::findOrCreate('product.create', 'web');

    $admin = User::factory()->create(['branch_id' => null]);
    $admin->givePermissionTo(['product.delete', 'product.create']);

    return $admin;
}

function productDeletePayload(array $overrides = []): array
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

test('product with only initial stock batches can be deleted', function () {
    $admin = productDeleteAdmin();
    seedAccountingAccounts(branchId: Branch::MAIN_BRANCH_ID);

    $payload = productDeletePayload([
        'initial_stock' => '10',
        'purchase_price' => '50',
        'sale_price' => '80',
    ]);

    $this->actingAs($admin)->post(route('product.store'), $payload)->assertRedirect(route('product.index'));

    $product = Product::query()->where('name', $payload['name'])->first();

    expect($product)->not->toBeNull()
        ->and(Batch::query()->where('product_id', $product->id)->exists())->toBeTrue()
        ->and(ProductInOutLog::query()->where('product_id', $product->id)->exists())->toBeTrue();

    $this->actingAs($admin)
        ->delete(route('product.destroy', $product))
        ->assertRedirect(route('product.index'))
        ->assertSessionHas('success');

    expect(Product::query()->whereKey($product->id)->exists())->toBeFalse()
        ->and(Batch::query()->where('product_id', $product->id)->exists())->toBeFalse()
        ->and(ProductInOutLog::query()->where('product_id', $product->id)->exists())->toBeFalse();
});

test('deleting product with supplier initial stock settlement reverses accounts and supplier balance', function () {
    $admin = productDeleteAdmin();
    $cash = seedAccountingAccounts(branchId: Branch::MAIN_BRANCH_ID);
    $supplier = Supplier::factory()->create(['branch_id' => Branch::MAIN_BRANCH_ID]);
    $inventory = SystemAccountService::resolve(SystemAccountKey::ProductInventory, Branch::MAIN_BRANCH_ID);

    $initialCashBalance = (float) $cash->fresh()->current_balance;
    $initialInventoryBalance = (float) $inventory->fresh()->current_balance;

    $payload = productDeletePayload([
        'initial_stock' => '20',
        'purchase_price' => '80',
        'initial_stock_supplier_id' => (string) $supplier->id,
        'initial_stock_paid_amount' => '500',
        'initial_stock_payment_account_id' => (string) $cash->id,
    ]);

    $this->actingAs($admin)->post(route('product.store'), $payload)->assertRedirect(route('product.index'));

    $product = Product::query()->where('name', $payload['name'])->first();

    expect((float) $cash->fresh()->current_balance)->toBe(round($initialCashBalance - 500, 2))
        ->and((float) $inventory->fresh()->current_balance)->toBe(round($initialInventoryBalance + 1600, 2))
        ->and((float) $supplier->fresh()->balance)->toBe(1100.0);

    $this->actingAs($admin)
        ->delete(route('product.destroy', $product))
        ->assertRedirect(route('product.index'))
        ->assertSessionHas('success');

    expect(Product::query()->whereKey($product->id)->exists())->toBeFalse()
        ->and((float) $cash->fresh()->current_balance)->toBe($initialCashBalance)
        ->and((float) $inventory->fresh()->current_balance)->toBe($initialInventoryBalance)
        ->and((float) $supplier->fresh()->balance)->toBe(0.0)
        ->and(Transaction::query()
            ->where('source_type', Product::class)
            ->where('source_id', $product->id)
            ->exists())->toBeFalse();
});

test('deleting product with opening balance initial stock reverses inventory accounting', function () {
    $admin = productDeleteAdmin();
    seedAccountingAccounts(branchId: Branch::MAIN_BRANCH_ID);
    $inventory = SystemAccountService::resolve(SystemAccountKey::ProductInventory, Branch::MAIN_BRANCH_ID);
    $openingBalance = SystemAccountService::resolve(SystemAccountKey::OpeningBalanceClearing, Branch::MAIN_BRANCH_ID);
    $capital = SystemAccountService::resolve(SystemAccountKey::OwnersCapital, Branch::MAIN_BRANCH_ID);

    $initialInventoryBalance = (float) $inventory->fresh()->current_balance;
    $initialOpeningBalance = (float) $openingBalance->fresh()->current_balance;
    $initialCapitalBalance = (float) $capital->fresh()->current_balance;

    $payload = productDeletePayload([
        'initial_stock' => '10',
        'purchase_price' => '50',
        'sale_price' => '80',
    ]);

    $this->actingAs($admin)->post(route('product.store'), $payload)->assertRedirect(route('product.index'));

    $product = Product::query()->where('name', $payload['name'])->first();
    $record = ProductInitialStock::query()->where('product_id', $product->id)->first();

    expect($record)->not->toBeNull()
        ->and((float) $inventory->fresh()->current_balance)->toBe(round($initialInventoryBalance + 500, 2))
        ->and((float) $openingBalance->fresh()->current_balance)->toBe(round($initialOpeningBalance, 2))
        ->and((float) $capital->fresh()->current_balance)->toBe(round($initialCapitalBalance + 500, 2));

    $this->actingAs($admin)
        ->delete(route('product.destroy', $product))
        ->assertRedirect(route('product.index'))
        ->assertSessionHas('success');

    expect(Product::query()->whereKey($product->id)->exists())->toBeFalse()
        ->and((float) $inventory->fresh()->current_balance)->toBe($initialInventoryBalance)
        ->and((float) $openingBalance->fresh()->current_balance)->toBe($initialOpeningBalance)
        ->and((float) $capital->fresh()->current_balance)->toBe($initialCapitalBalance)
        ->and(Transaction::query()
            ->where('source_type', ProductInitialStock::class)
            ->where('source_id', $record->id)
            ->exists())->toBeFalse();
});

test('product with sales history and zero stock is archived instead of deleted', function () {
    $admin = productDeleteAdmin();

    $product = Product::factory()->create([
        'branch_id' => Branch::MAIN_BRANCH_ID,
        'status' => 1,
        'visible' => 'yes',
    ]);

    $product->sellProducts()->create([
        'sell_id' => Sell::factory()->create(['branch_id' => Branch::MAIN_BRANCH_ID])->id,
        'quantity' => 1,
        'unit_price' => 100,
    ]);

    $this->actingAs($admin)
        ->delete(route('product.destroy', $product))
        ->assertRedirect(route('product.index'))
        ->assertSessionHas('success');

    $product->refresh();

    expect(Product::query()->whereKey($product->id)->exists())->toBeTrue()
        ->and($product->status)->toBe(0)
        ->and($product->visible)->toBe('no');
});

test('product with only damage history and zero stock is archived instead of deleted', function () {
    $admin = productDeleteAdmin();

    $product = Product::factory()->create([
        'branch_id' => Branch::MAIN_BRANCH_ID,
        'status' => 1,
        'visible' => 'yes',
    ]);

    DamageProduct::query()->create([
        'branch_id' => Branch::MAIN_BRANCH_ID,
        'damage_id' => Damage::query()->create([
            'branch_id' => Branch::MAIN_BRANCH_ID,
            'user_id' => $admin->id,
            'date' => now()->toDateString(),
        ])->id,
        'product_id' => $product->id,
        'quantity' => 1,
    ]);

    $this->actingAs($admin)
        ->delete(route('product.destroy', $product))
        ->assertRedirect(route('product.index'))
        ->assertSessionHas('success');

    $product->refresh();

    expect(Product::query()->whereKey($product->id)->exists())->toBeTrue()
        ->and($product->status)->toBe(0)
        ->and($product->visible)->toBe('no');
});

test('product with remaining stock cannot be removed even when it has transaction history', function () {
    $admin = productDeleteAdmin();
    seedAccountingAccounts(branchId: Branch::MAIN_BRANCH_ID);

    $payload = productDeletePayload([
        'initial_stock' => '5',
        'purchase_price' => '50',
        'sale_price' => '80',
    ]);

    $this->actingAs($admin)->post(route('product.store'), $payload)->assertRedirect(route('product.index'));

    $product = Product::query()->where('name', $payload['name'])->first();

    $product->sellProducts()->create([
        'sell_id' => Sell::factory()->create(['branch_id' => Branch::MAIN_BRANCH_ID])->id,
        'quantity' => 1,
        'unit_price' => 80,
    ]);

    $this->actingAs($admin)
        ->delete(route('product.destroy', $product))
        ->assertRedirect()
        ->assertSessionHas('error');

    $product->refresh();

    expect($product->status)->toBe(1);
});
