<?php

use App\Enums\AccountType;
use App\Enums\CommonStatus;
use App\Enums\SaleType;
use App\Enums\VoucherType;
use App\Http\Controllers\Reports\ReportController;
use App\Models\Batch;
use App\Models\Branch;
use App\Models\ChartOfAccount;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Sell;
use App\Models\User;
use App\Models\Voucher;
use App\Support\AdminNavigation;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

function reportUser(array $permissions = []): User
{
    $branch = Branch::factory()->create();
    $user = User::factory()->create(['branch_id' => $branch->id]);

    foreach ($permissions as $permission) {
        Permission::findOrCreate($permission, 'web');
        $user->givePermissionTo($permission);
    }

    return $user;
}

/**
 * @return array<string, array{path: string, permission: string, component: string}>
 */
function reportRoutes(): array
{
    return [
        'customer-ledger' => [
            'path' => '/report/customer-ledger',
            'permission' => ReportController::PERMISSION_CUSTOMER_LEDGER,
            'component' => 'admin/reports/customer-ledger',
        ],
        'cash-flow' => [
            'path' => '/report/cash-flow',
            'permission' => ReportController::PERMISSION_CASH_FLOW,
            'component' => 'admin/reports/cash-flow',
        ],
        'cash-flow-summary' => [
            'path' => '/report/cash-flow-summary',
            'permission' => ReportController::PERMISSION_CASH_FLOW_SUMMARY,
            'component' => 'admin/reports/cash-flow-summary',
        ],
        'daily-transactions' => [
            'path' => '/report/daily-transactions',
            'permission' => ReportController::PERMISSION_DAILY_TRANSACTIONS,
            'component' => 'admin/reports/daily-transactions',
        ],
        'date-wise-stock' => [
            'path' => '/report/date-wise-stock',
            'permission' => ReportController::PERMISSION_DATE_WISE_STOCK,
            'component' => 'admin/reports/date-wise-stock',
        ],
        'stock-ledger' => [
            'path' => '/report/stock-ledger',
            'permission' => ReportController::PERMISSION_STOCK_LEDGER,
            'component' => 'admin/reports/stock-ledger',
        ],
        'daily-summary' => [
            'path' => '/report/daily-summary',
            'permission' => ReportController::PERMISSION_DAILY_SUMMARY,
            'component' => 'admin/reports/daily-summary',
        ],
        'account-ledger' => [
            'path' => '/report/account-ledger',
            'permission' => ReportController::PERMISSION_ACCOUNT_LEDGER,
            'component' => 'admin/reports/account-ledger',
        ],
        'account-transactions' => [
            'path' => '/report/account-transactions',
            'permission' => ReportController::PERMISSION_ACCOUNT_TRANSACTIONS,
            'component' => 'admin/reports/account-transactions',
        ],
        'balance-sheet' => [
            'path' => '/report/balance-sheet',
            'permission' => ReportController::PERMISSION_BALANCE_SHEET,
            'component' => 'admin/reports/balance-sheet',
        ],
    ];
}

test('permissions sync creates all report permissions from config', function () {
    $this->artisan('permissions:sync')->assertExitCode(0);

    foreach (reportRoutes() as $report) {
        expect(Permission::where('name', $report['permission'])->exists())
            ->toBeTrue("Permission [{$report['permission']}] should exist after sync");
    }
});

test('guests cannot access reports', function () {
    $this->get('/report/customer-ledger')->assertRedirect(route('login'));
});

test('branch user without report permission is denied all report routes', function (string $key, array $report) {
    $this->artisan('permissions:sync');

    $this->actingAs(reportUser())
        ->get($report['path'])
        ->assertForbidden();
})->with(fn () => collect(reportRoutes())->map(fn ($report, $key) => [$key, $report]));

test('branch user with permission can access their assigned report', function (string $key, array $report) {
    $this->artisan('permissions:sync');

    $this->actingAs(reportUser([$report['permission']]))
        ->get($report['path'])
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component($report['component']));
})->with(fn () => collect(reportRoutes())->map(fn ($report, $key) => [$key, $report]));

test('user cannot access cash flow without cash flow permission', function () {
    $this->artisan('permissions:sync');

    $this->actingAs(reportUser([ReportController::PERMISSION_CUSTOMER_LEDGER]))
        ->get('/report/cash-flow')
        ->assertForbidden();
});

test('user can view customer ledger report with customer filter', function () {
    $this->artisan('permissions:sync');

    $user = reportUser([ReportController::PERMISSION_CUSTOMER_LEDGER]);
    $customer = Customer::factory()->create(['branch_id' => $user->branch_id]);

    $this->actingAs($user)
        ->get('/report/customer-ledger?customer_id='.$customer->id)
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/reports/customer-ledger')
            ->where('customer.id', $customer->id)
            ->has('entries')
            ->has('customers'));
});

test('superadmin can access all report routes', function () {
    $admin = User::factory()->create(['branch_id' => null]);

    $this->actingAs($admin);

    foreach (reportRoutes() as $report) {
        $this->get($report['path'])->assertOk();
    }
});

test('reports navigation only shows items the user may view', function () {
    $this->artisan('permissions:sync');

    $user = User::factory()->create(['branch_id' => Branch::factory()->create()->id]);
    $role = Role::create(['name' => 'report-viewer-'.uniqid(), 'guard_name' => 'web']);
    $role->givePermissionTo([
        ReportController::PERMISSION_DAILY_SUMMARY,
        ReportController::PERMISSION_BALANCE_SHEET,
    ]);
    $user->assignRole($role);

    $navigation = app(AdminNavigation::class)->build($user);
    $reports = collect($navigation)->firstWhere('title', 'Reports');

    expect($reports)->not->toBeNull()
        ->and(collect($reports['children'])->pluck('title')->all())->toEqual([
            'Balance Sheet',
            'Daily Summary',
        ])
        ->and(collect($reports['children'])->pluck('title')->all())->not->toContain('Cash Flow', 'Customer Ledger');
});

test('reports navigation includes all report links for superadmin', function () {
    $admin = User::factory()->create(['branch_id' => null]);

    $navigation = app(AdminNavigation::class)->build($admin);
    $reports = collect($navigation)->firstWhere('title', 'Reports');

    expect(collect($reports['children'])->pluck('title')->all())->toContain(
        'Customer Ledger',
        'Cash Flow',
        'Cash Flow Summary',
        'Daily Transactions',
        'Date Wise Stock',
        'Stock Ledger',
        'Daily Summary',
        'Account Ledger',
        'A/C Transactions',
        'Balance Sheet',
    );
});

test('branch user daily summary only includes their branch sales', function () {
    $this->artisan('permissions:sync');

    $date = '2026-06-04';
    $branchA = Branch::factory()->create();
    $branchB = Branch::factory()->create();
    $userA = reportUser([ReportController::PERMISSION_DAILY_SUMMARY]);
    $userA->update(['branch_id' => $branchA->id]);

    Sell::factory()->create([
        'branch_id' => $branchA->id,
        'type' => SaleType::Sale,
        'date' => $date,
        'gross_amount' => 1000,
        'paid_amount' => 200,
    ]);

    Sell::factory()->create([
        'branch_id' => $branchB->id,
        'type' => SaleType::Sale,
        'date' => $date,
        'gross_amount' => 5000,
        'paid_amount' => 0,
    ]);

    $this->actingAs($userA)
        ->get('/report/daily-summary?date='.$date)
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/reports/daily-summary')
            ->where('summary.sales.count', 1)
            ->where('summary.sales.gross', 1000)
            ->where('summary.sales.paid', 200));

    $admin = User::factory()->create(['branch_id' => null]);

    $this->actingAs($admin)
        ->get('/report/daily-summary?date='.$date)
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('summary.sales.count', 2)
            ->where('summary.sales.gross', 6000));
});

test('branch user daily summary only includes their branch expenses', function () {
    $this->artisan('permissions:sync');

    $date = '2026-06-05';
    $branchA = Branch::factory()->create();
    $branchB = Branch::factory()->create();
    $userA = reportUser([ReportController::PERMISSION_DAILY_SUMMARY]);
    $userA->update(['branch_id' => $branchA->id]);

    Voucher::query()->create([
        'type' => VoucherType::Expense,
        'voucher_no' => 'EXP-TEST-'.uniqid(),
        'date' => $date,
        'total_amount' => 300,
        'branch_id' => $branchA->id,
        'created_by' => $userA->id,
    ]);

    Voucher::query()->create([
        'type' => VoucherType::Expense,
        'voucher_no' => 'EXP-TEST-'.uniqid(),
        'date' => $date,
        'total_amount' => 1200,
        'branch_id' => $branchB->id,
        'created_by' => $userA->id,
    ]);

    $this->actingAs($userA)
        ->get('/report/daily-summary?date='.$date)
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/reports/daily-summary')
            ->where('summary.expenses.count', 1)
            ->where('summary.expenses.amount', 300));

    $admin = User::factory()->create(['branch_id' => null]);

    $this->actingAs($admin)
        ->get('/report/daily-summary?date='.$date)
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('summary.expenses.count', 2)
            ->where('summary.expenses.amount', 1500));
});

test('branch user customer ledger options exclude other branches', function () {
    $this->artisan('permissions:sync');

    $branchA = Branch::factory()->create();
    $branchB = Branch::factory()->create();
    $userA = reportUser([ReportController::PERMISSION_CUSTOMER_LEDGER]);
    $userA->update(['branch_id' => $branchA->id]);

    $ownCustomer = Customer::factory()->create(['branch_id' => $branchA->id, 'name' => 'Own Branch Customer']);
    Customer::factory()->create(['branch_id' => $branchB->id, 'name' => 'Other Branch Customer']);

    $this->actingAs($userA)
        ->get('/report/customer-ledger')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('customers', 1)
            ->where('customers.0.id', $ownCustomer->id));
});

test('branch user stock ledger only includes their branch movements', function () {
    $this->artisan('permissions:sync');

    $branchA = Branch::factory()->create();
    $branchB = Branch::factory()->create();
    $userA = reportUser([ReportController::PERMISSION_STOCK_LEDGER]);
    $userA->update(['branch_id' => $branchA->id]);

    $product = Product::factory()->create(['branch_id' => $branchA->id]);
    $date = '2026-06-05';

    $batchA = Batch::factory()->for($product)->withStock(10)->create(['branch_id' => $branchA->id]);
    $batchB = Batch::factory()->for($product)->withStock(20)->create(['branch_id' => $branchB->id]);

    $this->travelTo($date.' 10:00:00');
    $batchA->inStock(5);
    $batchB->inStock(8);
    $this->travelBack();

    $this->actingAs($userA)
        ->get('/report/stock-ledger?product_id='.$product->id.'&date_from='.$date.'&date_to='.$date)
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/reports/stock-ledger')
            ->where('isBranchScoped', true)
            ->where('mode', 'ledger')
            ->where('product.id', $product->id)
            ->has('entries', 1)
            ->where('entries.0.in', 5)
            ->where('totals.in', 5)
            ->where('totals.out', 0));
});

test('superadmin stock ledger shows all movements by default', function () {
    $this->artisan('permissions:sync');

    $admin = User::factory()->create(['branch_id' => null]);
    $branch = Branch::factory()->create();
    $product = Product::factory()->create();
    $batch = Batch::factory()->for($product)->withStock(12)->create(['branch_id' => $branch->id]);

    $this->travelTo('2026-06-05 10:00:00');
    $batch->inStock(4);
    $this->travelBack();

    $this->actingAs($admin)
        ->get('/report/stock-ledger')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/reports/stock-ledger')
            ->where('mode', 'overview')
            ->where('product', null)
            ->has('entries'));

    $this->actingAs($admin)
        ->get('/report/stock-ledger?product_id='.$product->id)
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('mode', 'ledger')
            ->where('product.id', $product->id)
            ->where('product.current_stock', 12)
            ->has('entries', 1)
            ->where('entries.0.in', 4));

    $this->actingAs($admin)
        ->get('/report/stock-ledger?product_id='.$product->id.'&branch_id='.$branch->id)
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('product.current_stock', 12)
            ->has('entries', 1));
});

test('branch user account ledger cannot see another branch account metadata', function () {
    $this->artisan('permissions:sync');

    $branchA = Branch::factory()->create();
    $branchB = Branch::factory()->create();
    $userA = reportUser([ReportController::PERMISSION_ACCOUNT_LEDGER]);
    $userA->update(['branch_id' => $branchA->id]);

    ChartOfAccount::$skipCodeGeneration = true;
    $branchBAccount = ChartOfAccount::query()->create([
        'code' => 'A999-TEST',
        'name' => 'Branch B Secret Account',
        'type' => AccountType::Asset,
        'source_type' => Branch::class,
        'source_id' => $branchB->id,
        'parent_id' => null,
        'current_balance' => 9999,
        'is_system' => false,
        'status' => CommonStatus::Active,
    ]);
    ChartOfAccount::$skipCodeGeneration = false;

    $this->actingAs($userA)
        ->get('/report/account-ledger?account_id='.$branchBAccount->id)
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/reports/account-ledger')
            ->where('account', null)
            ->where('entries', []));
});

test('stock ledger calculates opening balance before date range', function () {
    $this->artisan('permissions:sync');

    $branch = Branch::factory()->create();
    $user = reportUser([ReportController::PERMISSION_STOCK_LEDGER]);
    $user->update(['branch_id' => $branch->id]);

    $product = Product::factory()->create(['branch_id' => $branch->id]);
    $batch = Batch::factory()->for($product)->withStock(0)->create(['branch_id' => $branch->id]);

    $this->travelTo('2026-06-01 10:00:00');
    $batch->inStock(10);

    $this->travelTo('2026-06-05 11:00:00');
    $batch->outStock(3);

    $this->travelBack();

    $this->actingAs($user)
        ->get('/report/stock-ledger?product_id='.$product->id.'&date_from=2026-06-05&date_to=2026-06-05')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('opening_stock', 10)
            ->has('entries', 1)
            ->where('entries.0.out', 3)
            ->where('entries.0.balance', 7)
            ->where('totals.balance', 7));
});
