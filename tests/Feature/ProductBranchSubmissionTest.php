<?php

use App\Models\Branch;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Ledger;
use App\Models\Product;
use App\Models\ProductInitialStock;
use App\Models\Transaction;
use App\Models\Unit;
use App\Models\User;
use Spatie\Permission\Models\Permission;

function branchSubmissionAdmin(): User
{
    Permission::findOrCreate('product.view', 'web');
    Permission::findOrCreate('product.create', 'web');
    Permission::findOrCreate('product.update', 'web');

    $admin = User::factory()->create(['branch_id' => null]);
    $admin->givePermissionTo(['product.view', 'product.create', 'product.update']);

    return $admin;
}

function ensureSubmissionMainBranch(): Branch
{
    return Branch::query()->firstOrCreate(
        ['name' => Branch::MAIN_BRANCH_NAME],
        Branch::factory()->make(['name' => Branch::MAIN_BRANCH_NAME])->toArray(),
    );
}

function submissionProductPayload(int $branchId, array $overrides = []): array
{
    return array_merge([
        'branch_id' => (string) $branchId,
        'category_id' => (string) Category::factory()->create([
            'status' => 1,
            'branch_id' => $branchId,
        ])->id,
        'brand_id' => (string) Brand::factory()->create([
            'status' => 1,
            'branch_id' => $branchId,
        ])->id,
        'unit_id' => (string) Unit::query()->create([
            'branch_id' => $branchId,
            'name' => 'Unit '.fake()->unique()->numerify('####'),
            'status' => 1,
        ])->id,
        'name' => 'Submission Product '.fake()->unique()->numerify('######'),
        'purchase_price' => '100',
        'sale_price' => '150',
        'initial_stock' => '12',
        'visible' => 'no',
        'status' => '1',
    ], $overrides);
}

test('admin creating for specific non-main branch creates main and target copies', function () {
    $admin = branchSubmissionAdmin();
    $mainBranch = ensureSubmissionMainBranch();
    $operatingBranch = Branch::factory()->create();
    $mainBranchId = Branch::resolveMainBranchId();

    $payload = submissionProductPayload($operatingBranch->id);

    $this->actingAs($admin)
        ->post(route('product.store'), $payload)
        ->assertRedirect(route('product.index'));

    $products = Product::query()->where('name', $payload['name'])->get();

    expect($products)->toHaveCount(2)
        ->and($products->pluck('product_group_id')->filter()->unique())->toHaveCount(1);

    $mainCopy = $products->firstWhere('branch_id', $mainBranchId);
    $branchCopy = $products->firstWhere('branch_id', $operatingBranch->id);

    expect($mainCopy)->not->toBeNull()
        ->and($branchCopy)->not->toBeNull()
        ->and($mainCopy->source_branch_id)->toBeNull()
        ->and($mainCopy->received_at)->toBeNull()
        ->and((int) ProductInitialStock::query()->where('product_id', $mainCopy->id)->value('quantity'))->toBe(12)
        ->and(ProductInitialStock::query()->where('product_id', $branchCopy->id)->exists())->toBeFalse();
});

test('branch user product submission creates branch copy and pending main copy', function () {
    Permission::findOrCreate('product.create', 'web');

    ensureSubmissionMainBranch();
    $operatingBranch = Branch::factory()->create();
    $mainBranchId = Branch::resolveMainBranchId();
    $user = User::factory()->create(['branch_id' => $operatingBranch->id]);
    $user->givePermissionTo('product.create');

    $payload = submissionProductPayload($operatingBranch->id);
    unset($payload['branch_id']);

    $this->actingAs($user)
        ->post(route('product.store'), $payload)
        ->assertRedirect(route('product.index'));

    $products = Product::query()->where('name', $payload['name'])->get();

    expect($products)->toHaveCount(2);

    $mainCopy = $products->firstWhere('branch_id', $mainBranchId);
    $branchCopy = $products->firstWhere('branch_id', $operatingBranch->id);

    expect($mainCopy)->not->toBeNull()
        ->and($branchCopy)->not->toBeNull()
        ->and($mainCopy->source_branch_id)->toBe($operatingBranch->id)
        ->and($mainCopy->received_at)->toBeNull()
        ->and((int) ProductInitialStock::query()->where('product_id', $branchCopy->id)->value('quantity'))->toBe(12)
        ->and(ProductInitialStock::query()->where('product_id', $mainCopy->id)->exists())->toBeFalse();
});

test('pending main branch submission is hidden from main product list until received', function () {
    $admin = branchSubmissionAdmin();

    ensureSubmissionMainBranch();
    $operatingBranch = Branch::factory()->create();
    $mainBranchId = Branch::resolveMainBranchId();
    $user = User::factory()->create(['branch_id' => $operatingBranch->id]);
    $user->givePermissionTo('product.create');

    $payload = submissionProductPayload($operatingBranch->id);
    unset($payload['branch_id']);

    $this->actingAs($user)->post(route('product.store'), $payload);

    $mainCopy = Product::query()
        ->where('name', $payload['name'])
        ->where('branch_id', $mainBranchId)
        ->first();

    $this->actingAs($admin)
        ->get(route('product.index', ['branch_id' => $mainBranchId]))
        ->assertOk()
        ->assertInertia(function ($page) use ($mainCopy) {
            $page->component('admin/product/index');

            $pending = collect($page->toArray()['props']['pendingReceiveProducts'] ?? []);
            $listed = collect($page->toArray()['props']['products']['data'] ?? []);

            expect($pending->where('slug', $mainCopy->slug))->toHaveCount(1)
                ->and($pending->firstWhere('slug', $mainCopy->slug)['stock_summary']['total'])->toBe(12)
                ->and($listed->pluck('slug'))->not->toContain($mainCopy->slug);
        });
});

test('admin can receive pending branch submission and product appears in main list', function () {
    $admin = branchSubmissionAdmin();

    ensureSubmissionMainBranch();
    $operatingBranch = Branch::factory()->create(['name' => 'Gulshan Receive Branch']);
    $mainBranchId = Branch::resolveMainBranchId();
    $user = User::factory()->create(['branch_id' => $operatingBranch->id]);
    $user->givePermissionTo('product.create');

    $payload = submissionProductPayload($operatingBranch->id, ['initial_stock' => '8']);
    unset($payload['branch_id']);

    $this->actingAs($user)->post(route('product.store'), $payload);

    $mainCopy = Product::query()
        ->where('name', $payload['name'])
        ->where('branch_id', $mainBranchId)
        ->firstOrFail();

    $this->actingAs($admin)
        ->post(route('product.receive', $mainCopy))
        ->assertRedirect(route('product.index'))
        ->assertSessionHas('success');

    $mainCopy->refresh();

    expect($mainCopy->received_at)->not->toBeNull();

    $this->actingAs($admin)
        ->get(route('product.index', ['branch_id' => $mainBranchId]))
        ->assertOk()
        ->assertInertia(function ($page) use ($mainCopy) {
            $page->component('admin/product/index');

            $pending = collect($page->toArray()['props']['pendingReceiveProducts'] ?? []);
            $listed = collect($page->toArray()['props']['products']['data'] ?? []);

            expect($pending->where('slug', $mainCopy->slug))->toHaveCount(0)
                ->and($listed->pluck('slug'))->toContain($mainCopy->slug);
        });
});

test('pending branch submission branch copy is hidden from all-branches product list until received', function () {
    $admin = branchSubmissionAdmin();

    ensureSubmissionMainBranch();
    $operatingBranch = Branch::factory()->create();
    $mainBranchId = Branch::resolveMainBranchId();
    $user = User::factory()->create(['branch_id' => $operatingBranch->id]);
    $user->givePermissionTo('product.create');

    $payload = submissionProductPayload($operatingBranch->id);
    unset($payload['branch_id']);

    $this->actingAs($user)->post(route('product.store'), $payload);

    $mainCopy = Product::query()
        ->where('name', $payload['name'])
        ->where('branch_id', $mainBranchId)
        ->firstOrFail();

    $branchCopy = Product::query()
        ->where('name', $payload['name'])
        ->where('branch_id', $operatingBranch->id)
        ->firstOrFail();

    $this->actingAs($admin)
        ->get(route('product.index', ['branch_id' => 'all', 'search' => $payload['name']]))
        ->assertOk()
        ->assertInertia(function ($page) use ($mainCopy, $branchCopy) {
            $page->component('admin/product/index');

            $listed = collect($page->toArray()['props']['products']['data'] ?? []);

            expect($listed->pluck('slug'))->not->toContain($mainCopy->slug)
                ->and($listed->pluck('slug'))->not->toContain($branchCopy->slug);
        });
});

test('received branch submission shows zero main stock with branch initial stock summary in list', function () {
    $admin = branchSubmissionAdmin();

    ensureSubmissionMainBranch();
    $operatingBranch = Branch::factory()->create(['name' => 'Stock Hint Branch']);
    $mainBranchId = Branch::resolveMainBranchId();
    $user = User::factory()->create(['branch_id' => $operatingBranch->id]);
    $user->givePermissionTo('product.create');

    $payload = submissionProductPayload($operatingBranch->id, ['initial_stock' => '15']);
    unset($payload['branch_id']);

    $this->actingAs($user)->post(route('product.store'), $payload);

    $mainCopy = Product::query()
        ->where('name', $payload['name'])
        ->where('branch_id', $mainBranchId)
        ->firstOrFail();

    $this->actingAs($admin)->post(route('product.receive', $mainCopy));

    $this->actingAs($admin)
        ->get(route('product.index', ['branch_id' => $mainBranchId, 'search' => $payload['name']]))
        ->assertOk()
        ->assertInertia(function ($page) use ($mainCopy) {
            $page->component('admin/product/index');

            $listed = collect($page->toArray()['props']['products']['data'] ?? []);
            $row = $listed->firstWhere('slug', $mainCopy->slug);

            expect($row)->not->toBeNull()
                ->and((int) ($row['batches_sum_available'] ?? 0))->toBe(0)
                ->and($row['submission_stock_summary']['total'] ?? null)->toBe(15);
        });
});

test('pending main branch submission is hidden from all-branches product list until received', function () {
    $admin = branchSubmissionAdmin();

    ensureSubmissionMainBranch();
    $operatingBranch = Branch::factory()->create();
    $mainBranchId = Branch::resolveMainBranchId();
    $user = User::factory()->create(['branch_id' => $operatingBranch->id]);
    $user->givePermissionTo('product.create');

    $payload = submissionProductPayload($operatingBranch->id);
    unset($payload['branch_id']);

    $this->actingAs($user)->post(route('product.store'), $payload);

    $mainCopy = Product::query()
        ->where('name', $payload['name'])
        ->where('branch_id', $mainBranchId)
        ->first();

    $this->actingAs($admin)
        ->get(route('product.index', ['branch_id' => 'all', 'search' => $payload['name']]))
        ->assertOk()
        ->assertInertia(function ($page) use ($mainCopy) {
            $page->component('admin/product/index');

            $listed = collect($page->toArray()['props']['products']['data'] ?? []);

            expect($listed->pluck('slug'))->not->toContain($mainCopy->slug);
        });
});

test('branch user variant product submission creates main copy with zero variation stock', function () {
    Permission::findOrCreate('product.create', 'web');

    ensureSubmissionMainBranch();
    $operatingBranch = Branch::factory()->create();
    $mainBranchId = Branch::resolveMainBranchId();
    $user = User::factory()->create(['branch_id' => $operatingBranch->id]);
    $user->givePermissionTo('product.create');

    $payload = submissionProductPayload($operatingBranch->id, [
        'purchase_price' => null,
        'sale_price' => null,
        'initial_stock' => null,
        'combinations' => [
            [
                'variant' => 'Red / M',
                'variation_data' => ['label' => 'Red / M'],
                'sale_price' => '200',
                'purchase_price' => '120',
                'sku' => 'SKU-RED-M-'.fake()->unique()->numerify('####'),
                'stock' => '5',
            ],
            [
                'variant' => 'Blue / L',
                'variation_data' => ['label' => 'Blue / L'],
                'sale_price' => '200',
                'purchase_price' => '120',
                'sku' => 'SKU-BLUE-L-'.fake()->unique()->numerify('####'),
                'stock' => '3',
            ],
        ],
    ]);
    unset($payload['branch_id']);

    $this->actingAs($user)->post(route('product.store'), $payload);

    $mainCopy = Product::query()
        ->where('name', $payload['name'])
        ->where('branch_id', $mainBranchId)
        ->first();

    $branchCopy = Product::query()
        ->where('name', $payload['name'])
        ->where('branch_id', $operatingBranch->id)
        ->first();

    expect($mainCopy)->not->toBeNull()
        ->and($branchCopy)->not->toBeNull()
        ->and($mainCopy->source_branch_id)->toBe($operatingBranch->id)
        ->and($mainCopy->received_at)->toBeNull();

    $mainVariationStock = $mainCopy->variations->sum('stock');
    $branchVariationStock = $branchCopy->variations->sum('stock');

    expect($mainVariationStock)->toBe(0)
        ->and($branchVariationStock)->toBe(8);
});

test('branch submission initial stock posts accounting on branch accounts only', function () {
    Permission::findOrCreate('product.create', 'web');

    ensureSubmissionMainBranch();
    $operatingBranch = Branch::factory()->create();
    seedAccountingAccounts(branchId: $operatingBranch->id);

    $user = User::factory()->create(['branch_id' => $operatingBranch->id]);
    $user->givePermissionTo('product.create');

    $payload = submissionProductPayload($operatingBranch->id, [
        'initial_stock' => '10',
        'purchase_price' => '50',
    ]);
    unset($payload['branch_id']);

    $this->actingAs($user)->post(route('product.store'), $payload);

    $branchProduct = Product::query()
        ->where('name', $payload['name'])
        ->where('branch_id', $operatingBranch->id)
        ->firstOrFail();

    $initialStockRecord = ProductInitialStock::query()
        ->where('product_id', $branchProduct->id)
        ->whereNull('product_variation_id')
        ->first();

    expect($initialStockRecord)->not->toBeNull()
        ->and($initialStockRecord->quantity)->toBe(10)
        ->and($initialStockRecord->branch_id)->toBe($operatingBranch->id);

    $transaction = Transaction::query()
        ->where('source_type', ProductInitialStock::class)
        ->where('source_id', $initialStockRecord->id)
        ->first();

    expect($transaction)->not->toBeNull();

    $ledgers = Ledger::query()->where('transaction_id', $transaction->id)->get();

    expect(round($ledgers->sum('debit'), 2))->toBe(500.0)
        ->and(round($ledgers->sum('credit'), 2))->toBe(500.0);

    $mainCopy = Product::query()
        ->where('name', $payload['name'])
        ->where('branch_id', Branch::resolveMainBranchId())
        ->firstOrFail();

    expect(ProductInitialStock::query()->where('product_id', $mainCopy->id)->exists())->toBeFalse();
});
