<?php

use App\Enums\ProductLogType;
use App\Enums\StockAdjustmentType;
use App\Models\Batch;
use App\Models\Branch;
use App\Models\Product;
use App\Models\User;
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
