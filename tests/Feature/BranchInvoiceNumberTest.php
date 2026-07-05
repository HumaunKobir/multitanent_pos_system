<?php

use App\Enums\PurchaseType;
use App\Enums\SaleType;
use App\Models\Branch;
use App\Models\Damage;
use App\Models\ProductExchange;
use App\Models\Purchase;
use App\Models\PurchaseReturn;
use App\Models\SaleReturn;
use App\Models\Sell;
use App\Models\User;

test('inventory invoice numbers are scoped per branch', function () {
    $branchOne = Branch::factory()->create();
    $branchTwo = Branch::factory()->create();
    $userOne = User::factory()->create(['branch_id' => $branchOne->id]);
    $userTwo = User::factory()->create(['branch_id' => $branchTwo->id]);

    foreach (range(1, 10) as $index) {
        Purchase::query()->create([
            'branch_id' => $branchOne->id,
            'user_id' => $userOne->id,
            'date' => now()->toDateString(),
            'gross_amount' => 100 * $index,
            'paid_amount' => 100 * $index,
            'due_amount' => 0,
            'purchase_type' => PurchaseType::Purchase,
        ]);
    }

    $branchTwoPurchase = Purchase::query()->create([
        'branch_id' => $branchTwo->id,
        'user_id' => $userTwo->id,
        'date' => now()->toDateString(),
        'gross_amount' => 250,
        'paid_amount' => 250,
        'due_amount' => 0,
        'purchase_type' => PurchaseType::Purchase,
    ]);

    expect($branchTwoPurchase->fresh()->invoice_number)->toBe('INVP00000001')
        ->and($branchTwoPurchase->invoice_sequence)->toBe(1);

    $branchOneLatest = Purchase::query()
        ->where('branch_id', $branchOne->id)
        ->orderByDesc('invoice_sequence')
        ->first();

    expect($branchOneLatest?->invoice_number)->toBe('INVP00000010')
        ->and($branchOneLatest?->invoice_sequence)->toBe(10);
});

test('each inventory document type assigns its own branch sequence', function () {
    $branch = Branch::factory()->create();
    $user = User::factory()->create(['branch_id' => $branch->id]);
    $sell = Sell::query()->create([
        'branch_id' => $branch->id,
        'user_id' => $user->id,
        'date' => now()->toDateString(),
        'gross_amount' => 500,
        'paid_amount' => 500,
        'type' => SaleType::Sale,
    ]);

    $saleReturn = SaleReturn::query()->create([
        'branch_id' => $branch->id,
        'user_id' => $user->id,
        'sell_id' => $sell->id,
        'date' => now()->toDateString(),
        'gross_amount' => 100,
        'paid_amount' => 100,
    ]);

    $purchase = Purchase::query()->create([
        'branch_id' => $branch->id,
        'user_id' => $user->id,
        'date' => now()->toDateString(),
        'gross_amount' => 300,
        'paid_amount' => 300,
        'due_amount' => 0,
        'purchase_type' => PurchaseType::Purchase,
    ]);

    $purchaseReturn = PurchaseReturn::query()->create([
        'branch_id' => $branch->id,
        'user_id' => $user->id,
        'purchase_id' => $purchase->id,
        'date' => now()->toDateString(),
        'gross_amount' => 50,
        'paid_amount' => 50,
    ]);

    $damage = Damage::query()->create([
        'branch_id' => $branch->id,
        'user_id' => $user->id,
        'date' => now()->toDateString(),
    ]);

    $exchange = ProductExchange::query()->create([
        'branch_id' => $branch->id,
        'user_id' => $user->id,
        'sell_id' => $sell->id,
        'date' => now()->toDateString(),
        'gross_amount' => 200,
        'net_amount' => 200,
        'paid_amount' => 200,
    ]);

    expect($sell->fresh()->invoice_number)->toBe('INVS00000001')
        ->and($saleReturn->fresh()->invoice_number)->toBe('INVSR00000001')
        ->and($purchase->fresh()->invoice_number)->toBe('INVP00000001')
        ->and($purchaseReturn->fresh()->invoice_number)->toBe('INVPR00000001')
        ->and($damage->fresh()->invoice_number)->toBe('INVD00000001')
        ->and($exchange->fresh()->invoice_number)->toBe('INVX00000001');
});

test('purchase index exposes branch-scoped invoice numbers to the frontend', function () {
    $this->artisan('permissions:sync');

    $branchOne = Branch::factory()->create();
    $branchTwo = Branch::factory()->create();
    $user = User::factory()->create(['branch_id' => $branchTwo->id]);
    $user->givePermissionTo('inventory.purchase.view');

    foreach (range(1, 10) as $index) {
        Purchase::query()->create([
            'branch_id' => $branchOne->id,
            'user_id' => $user->id,
            'date' => now()->toDateString(),
            'gross_amount' => 100,
            'paid_amount' => 100,
            'due_amount' => 0,
            'purchase_type' => PurchaseType::Purchase,
        ]);
    }

    $purchase = Purchase::query()->create([
        'branch_id' => $branchTwo->id,
        'user_id' => $user->id,
        'date' => now()->toDateString(),
        'gross_amount' => 250,
        'paid_amount' => 250,
        'due_amount' => 0,
        'purchase_type' => PurchaseType::Purchase,
    ]);

    $this->actingAs($user)
        ->get('/inventory/purchase')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/inventory/purchase/index')
            ->has('purchases.data', 1)
            ->where('purchases.data.0.invoice_number', 'INVP00000001')
            ->where('purchases.data.0.id', $purchase->id));
});

test('paused sells do not consume branch invoice sequences', function () {
    $branch = Branch::factory()->create();
    $user = User::factory()->create(['branch_id' => $branch->id]);

    $pausedSell = Sell::query()->create([
        'branch_id' => $branch->id,
        'user_id' => $user->id,
        'date' => now()->toDateString(),
        'gross_amount' => 100,
        'paid_amount' => 0,
        'type' => SaleType::Paused,
    ]);

    $completedSell = Sell::query()->create([
        'branch_id' => $branch->id,
        'user_id' => $user->id,
        'date' => now()->toDateString(),
        'gross_amount' => 200,
        'paid_amount' => 200,
        'type' => SaleType::Sale,
    ]);

    expect($pausedSell->fresh()->invoice_sequence)->toBeNull()
        ->and($completedSell->fresh()->invoice_number)->toBe('INVS00000001');
});
