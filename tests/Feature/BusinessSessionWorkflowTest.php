<?php

use App\Enums\BusinessSessionOpeningMethod;
use App\Enums\BusinessSessionStatus;
use App\Enums\StockDistributionStatus;
use App\Http\Controllers\Account\BusinessSessionController;
use App\Models\Batch;
use App\Models\Branch;
use App\Models\Brand;
use App\Models\BusinessSession;
use App\Models\Category;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\StockDistribution;
use App\Models\Supplier;
use App\Models\SupplierPayment;
use App\Models\Transaction;
use App\Models\Unit;
use App\Models\User;
use App\Services\BusinessSessionService;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;

function workflowProductPayload(array $overrides = []): array
{
    $mainBranchId = ensureMainBranch();

    return array_merge([
        'branch_id' => (string) $mainBranchId,
        'category_id' => (string) Category::factory()->create(['status' => 1])->id,
        'brand_id' => (string) Brand::factory()->create(['status' => 1])->id,
        'unit_id' => (string) Unit::query()->create([
            'branch_id' => $mainBranchId,
            'name' => 'Unit '.fake()->unique()->numerify('####'),
            'status' => 1,
        ])->id,
        'name' => 'Workflow Product '.fake()->unique()->numerify('######'),
        'purchase_price' => '100',
        'sale_price' => '150',
        'visible' => 'no',
        'status' => '1',
    ], $overrides);
}

function workflowAdminUser(): User
{
    $mainBranchId = ensureMainBranch();
    seedAccountingAccounts(branchId: $mainBranchId);

    $admin = User::query()->find(1);

    if ($admin === null) {
        $admin = User::factory()->create(['branch_id' => $mainBranchId]);
    }

    foreach ([
        'product.create',
        'inventory.purchase.create',
        'inventory.stock-distribution.create',
        'inventory.stock-distribution.view',
        'party.supplier-payment.create',
        'party.supplier-payment.view',
        'branch.create',
        'user.create',
        BusinessSessionController::PERMISSION_START,
        BusinessSessionController::PERMISSION_CLOSE,
        BusinessSessionController::PERMISSION_VIEW,
        BusinessSessionController::PERMISSION_EXPORT,
        'dashboard.view',
    ] as $permission) {
        Permission::findOrCreate($permission, 'web');
        if (! $admin->can($permission)) {
            $admin->givePermissionTo($permission);
        }
    }

    return $admin;
}

function workflowBranchUserPermissions(User $user): void
{
    foreach ([
        'dashboard.view',
        BusinessSessionController::PERMISSION_START,
        BusinessSessionController::PERMISSION_CLOSE,
        BusinessSessionController::PERMISSION_VIEW,
        'inventory.stock-distribution.receive',
        'inventory.purchase.create',
        'inventory.purchase.view',
        'party.supplier-payment.create',
        'party.supplier-payment.view',
    ] as $permission) {
        Permission::findOrCreate($permission, 'web');
        $user->givePermissionTo($permission);
    }
}

function ensureWorkflowSession(User $user): BusinessSession
{
    $service = app(BusinessSessionService::class);
    $existing = $service->activeSessionForUser($user);

    if ($existing !== null) {
        return $existing;
    }

    return $service->start($user, BusinessSessionOpeningMethod::ManualFromPanel);
}

test('admin panel workflow: product, purchase, distribution, payment with business session', function () {
    $this->artisan('permissions:sync');
    $this->withoutVite();

    $mainBranchId = ensureMainBranch();
    $admin = workflowAdminUser();
    $cash = seedAccountingAccounts(branchId: $mainBranchId);

    $adminSession = ensureWorkflowSession($admin);

    $productPayload = workflowProductPayload([
        'name' => 'Workflow Product '.fake()->unique()->numerify('######'),
        'initial_stock' => '30',
        'purchase_price' => '500',
        'sale_price' => '750',
    ]);

    $this->actingAs($admin)
        ->post(route('product.store'), $productPayload)
        ->assertRedirect(route('product.index'));

    $product = Product::query()->where('name', $productPayload['name'])->first();
    expect($product)->not->toBeNull();

    $supplier = Supplier::factory()->create([
        'branch_id' => $mainBranchId,
        'balance' => 0,
    ]);

    $this->actingAs($admin)
        ->post('/inventory/purchase', [
            'supplier_id' => $supplier->id,
            'date' => now()->format('Y-m-d'),
            'discount' => '0',
            'vat' => '0',
            'paid_amount' => '1500',
            'payment_account_id' => $cash->id,
            'comment' => 'Workflow purchase',
            'items' => [
                [
                    'product_id' => $product->id,
                    'variation_id' => null,
                    'unit_price' => '1000',
                    'quantity' => '2',
                    'free_quantity' => '0',
                ],
            ],
        ])
        ->assertRedirect(route('inventory.purchase.index'));

    $purchase = Purchase::query()->latest('id')->first();
    expect($purchase)->not->toBeNull();

    $purchaseTransaction = Transaction::query()
        ->where('source_type', Purchase::class)
        ->where('source_id', $purchase->id)
        ->first();

    expect($purchaseTransaction)->not->toBeNull()
        ->and($purchaseTransaction->business_session_id)->toBe($adminSession->id);

    $branchName = 'Workflow Branch '.fake()->unique()->numerify('####');
    $branchEmail = 'workflow.branch.'.fake()->unique()->numerify('####').'@coolness.test';
    $branchPhone = fake()->unique()->numerify('017########');

    $this->actingAs($admin)
        ->post('/branch', [
            'name' => $branchName,
            'phone' => $branchPhone,
            'address' => 'Test Address, Dhaka',
        ])
        ->assertRedirect(route('branch.index'));

    $targetBranch = Branch::query()->where('name', $branchName)->first();
    expect($targetBranch)->not->toBeNull();

    $this->actingAs($admin)
        ->post('/user', [
            'branch_id' => $targetBranch->id,
            'name' => 'Workflow Branch User',
            'email' => $branchEmail,
            'phone' => fake()->unique()->numerify('018########'),
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'status' => 1,
        ])
        ->assertRedirect(route('user.index'));

    $branchUser = User::query()->where('email', $branchEmail)->first();
    expect($branchUser)->not->toBeNull();
    workflowBranchUserPermissions($branchUser);
    seedAccountingAccounts(branchId: $targetBranch->id);

    $this->actingAs($admin)
        ->post('/inventory/stock-distribution', [
            'to_branch_id' => $targetBranch->id,
            'date' => now()->format('Y-m-d'),
            'comment' => 'Workflow distribution',
            'items' => [
                [
                    'product_id' => $product->id,
                    'variation_id' => null,
                    'quantity' => '10',
                ],
            ],
        ])
        ->assertRedirect(route('inventory.stock-distribution.index'));

    $distribution = StockDistribution::query()->latest('id')->first();
    expect($distribution)->not->toBeNull()
        ->and($distribution->status)->toBe(StockDistributionStatus::Pending);

    $duePurchase = Purchase::query()->latest('id')->first();
    expect((float) $duePurchase->due_amount)->toBe(500.0);

    $this->actingAs($admin)
        ->post('/party/supplier-payment', [
            'supplier_id' => $supplier->id,
            'date' => now()->format('Y-m-d'),
            'payment_account_id' => $cash->id,
            'comment' => 'Workflow supplier payment',
            'allocations' => [
                ['purchase_id' => $duePurchase->id, 'amount' => 500],
            ],
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    $payment = SupplierPayment::query()->latest('id')->first();
    expect($payment)->not->toBeNull();

    $paymentTransaction = Transaction::query()
        ->where('source_type', SupplierPayment::class)
        ->where('source_id', $payment->id)
        ->first();

    expect($paymentTransaction)->not->toBeNull()
        ->and($paymentTransaction->business_session_id)->toBe($adminSession->id);

    $branchSession = ensureWorkflowSession($branchUser);

    $this->actingAs($branchUser)
        ->get('/branch-panel')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('branch-panel/dashboard')
            ->where('branchName', $branchName));

    $this->actingAs($branchUser)
        ->post("/inventory/stock-distribution/{$distribution->id}/receive")
        ->assertRedirect(route('inventory.stock-distribution.received'));

    $distribution->refresh();
    expect($distribution->status)->toBe(StockDistributionStatus::Received);

    $branchProduct = Product::query()
        ->where('branch_id', $targetBranch->id)
        ->where('name', $product->name)
        ->first();

    expect($branchProduct)->not->toBeNull();

    $branchSupplier = Supplier::factory()->create([
        'branch_id' => $targetBranch->id,
        'balance' => 0,
    ]);

    $branchCash = seedAccountingAccounts(branchId: $targetBranch->id);

    $this->actingAs($branchUser)
        ->post('/inventory/purchase', [
            'supplier_id' => $branchSupplier->id,
            'date' => now()->format('Y-m-d'),
            'discount' => '0',
            'vat' => '0',
            'paid_amount' => '0',
            'comment' => 'Branch workflow purchase',
            'items' => [
                [
                    'product_id' => $branchProduct->id,
                    'variation_id' => null,
                    'unit_price' => '600',
                    'quantity' => '1',
                    'free_quantity' => '0',
                ],
            ],
        ])
        ->assertRedirect(route('inventory.purchase.index'));

    $branchPurchase = Purchase::query()
        ->where('branch_id', $targetBranch->id)
        ->latest('id')
        ->first();

    expect($branchPurchase)->not->toBeNull();

    $branchPurchaseTransaction = Transaction::query()
        ->where('source_type', Purchase::class)
        ->where('source_id', $branchPurchase->id)
        ->first();

    expect($branchPurchaseTransaction)->not->toBeNull()
        ->and($branchPurchaseTransaction->business_session_id)->toBe($branchSession->id);

    $this->actingAs($branchUser)
        ->post('/party/supplier-payment', [
            'supplier_id' => $branchSupplier->id,
            'date' => now()->format('Y-m-d'),
            'payment_account_id' => $branchCash->id,
            'comment' => 'Branch workflow payment',
            'allocations' => [
                ['purchase_id' => $branchPurchase->id, 'amount' => 300],
            ],
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    $branchPaymentTransaction = Transaction::query()
        ->where('source_type', SupplierPayment::class)
        ->where('source_id', SupplierPayment::query()->latest('id')->value('id'))
        ->first();

    expect($branchPaymentTransaction)->not->toBeNull()
        ->and($branchPaymentTransaction->business_session_id)->toBe($branchSession->id);

    $this->actingAs($branchUser)
        ->getJson(route('accounts.daily-sessions.close-preview'))
        ->assertOk()
        ->assertJsonStructure(['session', 'report', 'can_export']);

    $this->actingAs($branchUser)
        ->post(route('accounts.daily-sessions.close-confirm'))
        ->assertRedirect(route('accounts.daily-sessions.index'));

    $branchSession->refresh();
    expect($branchSession->status)->toBe(BusinessSessionStatus::Closed);

    $this->actingAs($admin)
        ->getJson(route('accounts.daily-sessions.close-preview'))
        ->assertOk();

    $this->actingAs($admin)
        ->post(route('accounts.daily-sessions.close-confirm'))
        ->assertRedirect(route('accounts.daily-sessions.index'));

    $adminSession->refresh();
    expect($adminSession->status)->toBe(BusinessSessionStatus::Closed)
        ->and($adminSession->report_snapshot)->not->toBeNull();

    $sessionTransactionCount = Transaction::query()
        ->where('business_session_id', $adminSession->id)
        ->count();

    expect($sessionTransactionCount)->toBeGreaterThanOrEqual(2);

    $this->actingAs($admin)
        ->getJson(route('accounts.daily-sessions.report', $adminSession))
        ->assertOk()
        ->assertJsonPath('session.id', $adminSession->id);

    $mainBatch = Batch::query()
        ->where('product_id', $product->id)
        ->where('branch_id', $mainBranchId)
        ->first();

    expect($mainBatch)->not->toBeNull()
        ->and((float) $mainBatch->available)->toBeLessThan(30.0);
});
