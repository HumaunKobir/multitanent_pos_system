<?php

use App\Enums\AccountType;
use App\Enums\CommonStatus;
use App\Enums\ProductLogType;
use App\Enums\PurchaseType;
use App\Enums\SaleType;
use App\Enums\VoucherType;
use App\Http\Controllers\Reports\ReportController;
use App\Models\Batch;
use App\Models\Branch;
use App\Models\Brand;
use App\Models\Category;
use App\Models\ChartOfAccount;
use App\Models\Color;
use App\Models\Customer;
use App\Models\CustomerPayment;
use App\Models\Damage;
use App\Models\DamageProduct;
use App\Models\Product;
use App\Models\ProductExchange;
use App\Models\ProductInOutLog;
use App\Models\ProductVariation;
use App\Models\Promotion;
use App\Models\Purchase;
use App\Models\PurchaseReturn;
use App\Models\SaleReturn;
use App\Models\Sell;
use App\Models\SellProduct;
use App\Models\Size;
use App\Models\Supplier;
use App\Models\SupplierPayment;
use App\Models\Transaction;
use App\Models\Unit;
use App\Models\User;
use App\Models\Voucher;
use App\Services\ReportService;
use App\Support\AdminNavigation;
use Illuminate\Support\Str;
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
        'sales-summary' => [
            'path' => '/report/sales-summary',
            'permission' => ReportController::PERMISSION_SALES_SUMMARY,
            'component' => 'admin/reports/sales-summary',
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
        'Sales Summary',
        'Account Ledger',
        'A/C Transactions',
        'Balance Sheet',
    );
});

test('branch user daily summary only includes their branch sales', function () {
    $this->artisan('permissions:sync');

    $date = sprintf('2099-07-%02d', (hexdec(substr(md5(uniqid('', true)), 0, 4)) % 28) + 1);
    $branchA = Branch::factory()->create();
    $branchB = Branch::factory()->create();
    $userA = reportUser([ReportController::PERMISSION_DAILY_SUMMARY]);
    $userA->update(['branch_id' => $branchA->id]);
    $grossOwn = 1000 + random_int(1, 99);
    $grossPeer = 3000 + random_int(1, 99);

    Sell::factory()->create([
        'branch_id' => $branchA->id,
        'user_id' => $userA->id,
        'type' => SaleType::Sale,
        'date' => $date,
        'gross_amount' => $grossOwn,
        'paid_amount' => 200,
    ]);

    Sell::factory()->create([
        'branch_id' => $branchA->id,
        'user_id' => User::factory()->create(['branch_id' => $branchA->id])->id,
        'type' => SaleType::Sale,
        'date' => $date,
        'gross_amount' => $grossPeer,
        'paid_amount' => 0,
    ]);

    $grossOtherBranch = 5000 + random_int(1, 99);

    Sell::factory()->create([
        'branch_id' => $branchB->id,
        'type' => SaleType::Sale,
        'date' => $date,
        'gross_amount' => $grossOtherBranch,
        'paid_amount' => 0,
    ]);

    $this->actingAs($userA)
        ->get('/report/daily-summary?date='.$date)
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/reports/daily-summary')
            ->where('summary.sales.gross', $grossOwn + $grossPeer)
            ->where('summary.sales.paid', 200)
            ->where('summary.staff_breakdown', []));

    $admin = reportUser([ReportController::PERMISSION_DAILY_SUMMARY]);
    $admin->update(['branch_id' => null]);

    $this->actingAs($admin)
        ->get('/report/daily-summary?date='.$date.'&branch_id='.$branchA->id)
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('summary.sales.count', 2)
            ->where('summary.sales.gross', $grossOwn + $grossPeer));
});

test('branch user daily summary only includes their branch expenses', function () {
    $this->artisan('permissions:sync');

    $date = sprintf('2099-08-%02d', (hexdec(substr(md5(uniqid('', true)), 0, 4)) % 28) + 1);
    $branchA = Branch::factory()->create();
    $branchB = Branch::factory()->create();
    $userA = reportUser([ReportController::PERMISSION_DAILY_SUMMARY]);
    $userA->update(['branch_id' => $branchA->id]);
    $expenseOwn = 300 + random_int(1, 99);
    $expenseOtherBranch = 1200 + random_int(1, 99);

    Voucher::query()->create([
        'type' => VoucherType::Expense,
        'voucher_no' => 'EXP-TEST-'.uniqid(),
        'date' => $date,
        'total_amount' => $expenseOwn,
        'branch_id' => $branchA->id,
        'created_by' => $userA->id,
    ]);

    Voucher::query()->create([
        'type' => VoucherType::Expense,
        'voucher_no' => 'EXP-TEST-'.uniqid(),
        'date' => $date,
        'total_amount' => $expenseOtherBranch,
        'branch_id' => $branchB->id,
        'created_by' => $userA->id,
    ]);

    $this->actingAs($userA)
        ->get('/report/daily-summary?date='.$date)
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/reports/daily-summary')
            ->where('summary.expenses.count', 1)
            ->where('summary.expenses.amount', $expenseOwn));

    $admin = reportUser([ReportController::PERMISSION_DAILY_SUMMARY]);
    $admin->update(['branch_id' => null]);

    $this->actingAs($admin)
        ->get('/report/daily-summary?date='.$date)
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('summary.expenses.count', 2)
            ->where('summary.expenses.amount', $expenseOwn + $expenseOtherBranch));
});

test('main branch user sees branch and user filters on daily summary', function () {
    $this->artisan('permissions:sync');

    $mainBranchId = Branch::resolveMainBranchId();
    $mainUser = reportUser([ReportController::PERMISSION_DAILY_SUMMARY]);
    $mainUser->update(['branch_id' => $mainBranchId]);

    $this->actingAs($mainUser)
        ->get('/report/daily-summary')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/reports/daily-summary')
            ->where('isBranchScoped', false)
            ->has('branches')
            ->has('users'));
});

test('daily summary includes branch and user wise sales and purchase breakdown', function () {
    $this->artisan('permissions:sync');

    $date = '2099-01-15';
    $unique = uniqid();
    $branchA = Branch::factory()->create(['name' => 'Branch Alpha '.$unique]);
    $branchB = Branch::factory()->create(['name' => 'Branch Beta '.$unique]);
    $staffA = User::factory()->create(['branch_id' => $branchA->id, 'name' => 'Staff A '.$unique]);
    $staffB = User::factory()->create(['branch_id' => $branchB->id, 'name' => 'Staff B '.$unique]);
    $admin = reportUser([ReportController::PERMISSION_DAILY_SUMMARY]);
    $admin->update(['branch_id' => null]);
    $grossA = 1100 + random_int(1, 99);
    $grossB = 2600 + random_int(1, 99);
    $purchaseA = 650 + random_int(1, 49);

    Sell::factory()->create([
        'branch_id' => $branchA->id,
        'user_id' => $staffA->id,
        'type' => SaleType::Sale,
        'date' => $date,
        'gross_amount' => $grossA,
        'paid_amount' => 400,
    ]);

    Sell::factory()->create([
        'branch_id' => $branchB->id,
        'user_id' => $staffB->id,
        'type' => SaleType::Sale,
        'date' => $date,
        'gross_amount' => $grossB,
        'paid_amount' => 2500,
    ]);

    Purchase::query()->create([
        'branch_id' => $branchA->id,
        'user_id' => $staffA->id,
        'date' => $date,
        'gross_amount' => $purchaseA,
        'paid_amount' => $purchaseA,
        'due_amount' => 0,
        'serial' => 'INVP-DS-'.$unique,
        'purchase_type' => PurchaseType::Purchase,
    ]);

    $this->actingAs($admin)
        ->get('/report/daily-summary?date='.$date)
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/reports/daily-summary')
            ->where('isBranchScoped', false)
            ->has('branches')
            ->has('users')
            ->where('summary.staff_breakdown', []));

    $this->actingAs($admin)
        ->get('/report/daily-summary?date='.$date.'&branch_id='.$branchA->id)
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('summary.staff_breakdown', function ($rows) use ($branchA, $staffA, $grossA, $purchaseA): bool {
                $byUser = collect($rows)->keyBy('user_id');

                return isset($byUser[$staffA->id])
                    && (float) $byUser[$staffA->id]['sales']['gross'] === (float) $grossA
                    && (float) $byUser[$staffA->id]['purchases']['gross'] === (float) $purchaseA
                    && (int) $byUser[$staffA->id]['branch_id'] === $branchA->id
                    && count($byUser[$staffA->id]['sales_items'] ?? []) === 1
                    && count($byUser[$staffA->id]['purchases_items'] ?? []) === 1
                    && (float) ($byUser[$staffA->id]['sales_items'][0]['gross'] ?? 0) === (float) $grossA
                    && (float) ($byUser[$staffA->id]['purchases_items'][0]['gross'] ?? 0) === (float) $purchaseA;
            }));
});

test('main branch admin can filter daily summary by branch and user', function () {
    $this->artisan('permissions:sync');

    $date = '2026-06-07';
    $branchA = Branch::factory()->create();
    $branchB = Branch::factory()->create();
    $staffA = User::factory()->create(['branch_id' => $branchA->id]);
    $staffB = User::factory()->create(['branch_id' => $branchB->id]);
    $admin = reportUser([ReportController::PERMISSION_DAILY_SUMMARY]);
    $admin->update(['branch_id' => null]);

    Sell::factory()->create([
        'branch_id' => $branchA->id,
        'user_id' => $staffA->id,
        'type' => SaleType::Sale,
        'date' => $date,
        'gross_amount' => 800,
        'paid_amount' => 800,
    ]);

    Sell::factory()->create([
        'branch_id' => $branchB->id,
        'user_id' => $staffB->id,
        'type' => SaleType::Sale,
        'date' => $date,
        'gross_amount' => 3000,
        'paid_amount' => 0,
    ]);

    $this->actingAs($admin)
        ->get('/report/daily-summary?date='.$date.'&branch_id='.$branchA->id)
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('summary.sales.count', 1)
            ->where('summary.sales.gross', 800)
            ->has('summary.staff_breakdown', 1)
            ->where('summary.staff_breakdown.0.branch_id', $branchA->id)
            ->where('summary.staff_breakdown.0.user_id', $staffA->id));

    $this->actingAs($admin)
        ->get('/report/daily-summary?date='.$date.'&user_id='.$staffB->id)
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('summary.sales.count', 1)
            ->where('summary.sales.gross', 3000)
            ->has('summary.staff_breakdown', 1)
            ->where('summary.staff_breakdown.0.user_id', $staffB->id));
});

test('branch user daily summary shows branch totals without user breakdown', function () {
    $this->artisan('permissions:sync');

    $date = '2026-06-08';
    $branchA = Branch::factory()->create();
    $branchB = Branch::factory()->create();
    $staffA = User::factory()->create(['branch_id' => $branchA->id]);
    $branchUser = reportUser([ReportController::PERMISSION_DAILY_SUMMARY]);
    $branchUser->update(['branch_id' => $branchA->id]);

    Sell::factory()->create([
        'branch_id' => $branchA->id,
        'user_id' => $staffA->id,
        'type' => SaleType::Sale,
        'date' => $date,
        'gross_amount' => 1200,
        'paid_amount' => 1200,
    ]);

    Sell::factory()->create([
        'branch_id' => $branchB->id,
        'user_id' => User::factory()->create(['branch_id' => $branchB->id])->id,
        'type' => SaleType::Sale,
        'date' => $date,
        'gross_amount' => 9000,
        'paid_amount' => 9000,
    ]);

    $this->actingAs($branchUser)
        ->get('/report/daily-summary?date='.$date)
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('isBranchScoped', true)
            ->where('branches', [])
            ->where('summary.sales.count', 1)
            ->where('summary.sales.gross', 1200)
            ->where('summary.staff_breakdown', []));

    Sell::factory()->create([
        'branch_id' => $branchA->id,
        'user_id' => $branchUser->id,
        'type' => SaleType::Sale,
        'date' => $date,
        'gross_amount' => 550,
        'paid_amount' => 550,
    ]);

    $this->actingAs($branchUser)
        ->get('/report/daily-summary?date='.$date)
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('summary.sales.count', 2)
            ->where('summary.sales.gross', 1750)
            ->where('summary.staff_breakdown', []));
});

test('branch user daily summary shows all branch supplier payments and customer collections', function () {
    $this->artisan('permissions:sync');

    $date = '2026-06-09';
    $branch = Branch::factory()->create();
    $otherStaff = User::factory()->create(['branch_id' => $branch->id]);
    $branchUser = reportUser([ReportController::PERMISSION_DAILY_SUMMARY]);
    $branchUser->update(['branch_id' => $branch->id]);
    $supplier = Supplier::factory()->create(['branch_id' => $branch->id]);
    $customer = Customer::factory()->create(['branch_id' => $branch->id]);

    SupplierPayment::query()->create([
        'branch_id' => $branch->id,
        'supplier_id' => $supplier->id,
        'date' => $date,
        'amount' => 400,
        'serial' => 'INVSP-BU-A',
        'created_by' => $otherStaff->id,
    ]);

    CustomerPayment::query()->create([
        'branch_id' => $branch->id,
        'customer_id' => $customer->id,
        'date' => $date,
        'amount' => 250,
        'serial' => 'INVCP-BU-A',
        'created_by' => $otherStaff->id,
    ]);

    $this->actingAs($branchUser)
        ->get('/report/daily-summary?date='.$date)
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('summary.supplier_payments.count', 1)
            ->where('summary.supplier_payments.amount', 400)
            ->where('summary.customer_collections.count', 1)
            ->where('summary.customer_collections.amount', 250));
});

test('daily summary branch totals match staff breakdown including line discounts and payments', function () {
    $this->artisan('permissions:sync');

    $date = '2026-06-25';
    $unique = uniqid();
    $branch = Branch::factory()->create(['name' => 'Branch '.$unique]);
    $staffA = User::factory()->create(['branch_id' => $branch->id, 'name' => 'Staff A '.$unique]);
    $staffB = User::factory()->create(['branch_id' => $branch->id, 'name' => 'Staff B '.$unique]);
    $admin = reportUser([ReportController::PERMISSION_DAILY_SUMMARY]);
    $admin->update(['branch_id' => null]);
    $product = Product::factory()->create();
    $supplier = Supplier::factory()->create(['branch_id' => $branch->id]);
    $customer = Customer::factory()->create(['branch_id' => $branch->id]);

    $sellA = Sell::factory()->create([
        'branch_id' => $branch->id,
        'user_id' => $staffA->id,
        'type' => SaleType::Sale,
        'date' => $date,
        'gross_amount' => 200,
        'paid_amount' => 195.50,
    ]);

    SellProduct::query()->create([
        'branch_id' => $branch->id,
        'sell_id' => $sellA->id,
        'product_id' => $product->id,
        'quantity' => 1,
        'unit_price' => 200,
        'discount' => 4.50,
        'batches' => [],
    ]);

    Sell::factory()->create([
        'branch_id' => $branch->id,
        'user_id' => $staffB->id,
        'type' => SaleType::Sale,
        'date' => $date,
        'gross_amount' => 200,
        'paid_amount' => 200,
    ]);

    SupplierPayment::query()->create([
        'branch_id' => $branch->id,
        'supplier_id' => $supplier->id,
        'date' => $date,
        'amount' => 150,
        'serial' => 'INVSP-DS-'.$unique.'-A',
        'created_by' => $staffA->id,
    ]);

    SupplierPayment::query()->create([
        'branch_id' => $branch->id,
        'supplier_id' => $supplier->id,
        'date' => $date,
        'amount' => 100,
        'serial' => 'INVSP-DS-'.$unique.'-B',
        'created_by' => $staffB->id,
    ]);

    CustomerPayment::query()->create([
        'branch_id' => $branch->id,
        'customer_id' => $customer->id,
        'date' => $date,
        'amount' => 80,
        'serial' => 'INVCP-DS-'.$unique.'-A',
        'created_by' => $staffA->id,
    ]);

    CustomerPayment::query()->create([
        'branch_id' => $branch->id,
        'customer_id' => $customer->id,
        'date' => $date,
        'amount' => 120,
        'serial' => 'INVCP-DS-'.$unique.'-B',
        'created_by' => $staffB->id,
    ]);

    $this->actingAs($admin)
        ->get('/report/daily-summary?date='.$date.'&branch_id='.$branch->id)
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('summary.sales.gross', 395.50)
            ->where('summary.supplier_payments.amount', 250)
            ->where('summary.customer_collections.amount', 200)
            ->has('summary.records.sales', 2)
            ->has('summary.records.supplier_payments', 2)
            ->where('summary.records.supplier_payments.0.gross', 100)
            ->has('summary.records.customer_collections', 2)
            ->where('summary.records.customer_collections.0.gross', 120)
            ->where('summary.staff_breakdown', function ($rows): bool {
                $rows = collect($rows);
                $salesGross = round($rows->sum(fn (array $row) => (float) ($row['sales']['gross'] ?? 0)), 2);
                $supplierPaid = round($rows->sum(fn (array $row) => (float) ($row['supplier_payments']['amount'] ?? 0)), 2);
                $collections = round($rows->sum(fn (array $row) => (float) ($row['customer_collections']['amount'] ?? 0)), 2);

                return $salesGross === 395.50
                    && $supplierPaid === 250.0
                    && $collections === 200.0
                    && $rows->count() === 2;
            }));

    $this->actingAs($admin)
        ->get('/report/daily-summary?date='.$date.'&user_id='.$staffA->id)
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('summary.sales.gross', 195.50)
            ->where('summary.supplier_payments.amount', 150)
            ->where('summary.customer_collections.amount', 80));

    $this->actingAs($admin)
        ->get('/report/daily-summary?date='.$date.'&user_id='.$staffB->id)
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('summary.sales.gross', 200)
            ->where('summary.supplier_payments.amount', 100)
            ->where('summary.customer_collections.amount', 120));
});

test('daily summary applies all sell discount types and sale return net amount', function () {
    $this->artisan('permissions:sync');

    $date = '2026-06-25';
    $unique = uniqid();
    $branch = Branch::factory()->create(['name' => 'Branch '.$unique]);
    $staff = User::factory()->create(['branch_id' => $branch->id]);
    $admin = reportUser([ReportController::PERMISSION_DAILY_SUMMARY]);
    $admin->update(['branch_id' => null]);
    $product = Product::factory()->create();

    $sell = Sell::factory()->create([
        'branch_id' => $branch->id,
        'user_id' => $staff->id,
        'type' => SaleType::Sale,
        'date' => $date,
        'gross_amount' => 1000,
        'discount' => 50,
        'special_discount_amount' => 100,
        'promotion_discount_total' => 75,
        'coin_discount_amount' => 25,
        'round_off_amount' => 10,
        'vat' => 0,
        'paid_amount' => 785,
    ]);

    SellProduct::query()->create([
        'branch_id' => $branch->id,
        'sell_id' => $sell->id,
        'product_id' => $product->id,
        'quantity' => 1,
        'unit_price' => 1000,
        'discount' => 30,
        'batches' => [],
    ]);

    expect($sell->fresh()->net_amount)->toBe(785.0);

    SaleReturn::query()->create([
        'branch_id' => $branch->id,
        'user_id' => $staff->id,
        'sell_id' => $sell->id,
        'customer_id' => null,
        'date' => $date,
        'gross_amount' => 300,
        'discount_amount' => 45,
        'paid_amount' => 200,
    ]);
    $saleReturn = SaleReturn::query()->where('sell_id', $sell->id)->firstOrFail();

    $supplier = Supplier::factory()->create(['branch_id' => $branch->id]);
    $purchase = Purchase::factory()->create([
        'branch_id' => $branch->id,
        'user_id' => $staff->id,
        'supplier_id' => $supplier->id,
        'date' => $date,
    ]);

    PurchaseReturn::query()->create([
        'branch_id' => $branch->id,
        'user_id' => $staff->id,
        'purchase_id' => $purchase->id,
        'supplier_id' => $supplier->id,
        'date' => $date,
        'gross_amount' => 500,
        'discount' => 60,
        'vat' => 40,
        'paid_amount' => 400,
        'due_amount' => 80,
    ]);
    $purchaseReturn = PurchaseReturn::query()->where('purchase_id', $purchase->id)->firstOrFail();

    ProductExchange::query()->create([
        'branch_id' => $branch->id,
        'user_id' => $staff->id,
        'sell_id' => $sell->id,
        'customer_id' => null,
        'date' => $date,
        'gross_amount' => 200,
        'net_amount' => 175,
        'paid_amount' => 25,
        'price_difference' => 25,
    ]);
    $productExchange = ProductExchange::query()->where('sell_id', $sell->id)->firstOrFail();

    $damageBatch = Batch::factory()->for($product)->withStock(10)->create([
        'branch_id' => $branch->id,
        'purchase_price' => 50,
    ]);
    $damage = Damage::query()->create([
        'branch_id' => $branch->id,
        'user_id' => $staff->id,
        'date' => $date,
    ]);
    DamageProduct::query()->create([
        'branch_id' => $branch->id,
        'damage_id' => $damage->id,
        'product_id' => $product->id,
        'variation_id' => null,
        'quantity' => 2,
        'batches' => [$damageBatch->id => 2],
    ]);

    $this->actingAs($admin)
        ->get('/report/daily-summary?date='.$date.'&branch_id='.$branch->id)
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('summary.sales.gross', 810)
            ->where('summary.sale_returns.amount', 255)
            ->where('summary.sale_returns.paid', 200)
            ->where('summary.sale_returns.due', 55)
            ->where('summary.purchase_returns.amount', 480)
            ->where('summary.purchase_returns.count', 1)
            ->where('summary.purchase_returns.paid', 400)
            ->where('summary.purchase_returns.due', 80)
            ->where('summary.product_exchanges.amount', 175)
            ->where('summary.product_exchanges.count', 1)
            ->where('summary.product_exchanges.paid', 25)
            ->where('summary.product_exchanges.difference', 25)
            ->where('summary.damages.count', 1)
            ->where('summary.damages.amount', 100)
            ->where('summary.staff_breakdown.0.sales.gross', 810)
            ->where('summary.staff_breakdown.0.sale_returns.count', 1)
            ->where('summary.staff_breakdown.0.sale_returns.amount', 255)
            ->where('summary.staff_breakdown.0.product_exchanges.count', 1)
            ->where('summary.staff_breakdown.0.product_exchanges.amount', 175)
            ->where('summary.staff_breakdown.0.purchase_returns.count', 1)
            ->where('summary.staff_breakdown.0.purchase_returns.amount', 480)
            ->where('summary.staff_breakdown.0.damages.count', 1)
            ->where('summary.staff_breakdown.0.damages.amount', 100)
            ->has('summary.staff_breakdown.0.sales_items', 1)
            ->where('summary.staff_breakdown.0.sales_items.0.id', $sell->id)
            ->has('summary.staff_breakdown.0.sale_returns_items', 1)
            ->where('summary.staff_breakdown.0.sale_returns_items.0.id', $saleReturn->id)
            ->where('summary.staff_breakdown.0.sale_returns_items.0.gross', 255)
            ->has('summary.staff_breakdown.0.product_exchanges_items', 1)
            ->where('summary.staff_breakdown.0.product_exchanges_items.0.id', $productExchange->id)
            ->where('summary.staff_breakdown.0.product_exchanges_items.0.gross', 175)
            ->has('summary.staff_breakdown.0.purchase_returns_items', 1)
            ->where('summary.staff_breakdown.0.purchase_returns_items.0.id', $purchaseReturn->id)
            ->where('summary.staff_breakdown.0.purchase_returns_items.0.gross', 480)
            ->has('summary.staff_breakdown.0.damages_items', 1)
            ->where('summary.staff_breakdown.0.damages_items.0.id', $damage->id)
            ->where('summary.staff_breakdown.0.damages_items.0.gross', 100)
            ->has('summary.records.sales', 1)
            ->where('summary.records.sales.0.id', $sell->id)
            ->where('summary.records.sales.0.gross', 810)
            ->has('summary.records.sale_returns', 1)
            ->where('summary.records.sale_returns.0.id', $saleReturn->id)
            ->where('summary.records.sale_returns.0.gross', 255)
            ->has('summary.records.product_exchanges', 1)
            ->where('summary.records.product_exchanges.0.id', $productExchange->id)
            ->where('summary.records.product_exchanges.0.gross', 175)
            ->has('summary.records.purchase_returns', 1)
            ->where('summary.records.purchase_returns.0.id', $purchaseReturn->id)
            ->where('summary.records.purchase_returns.0.gross', 480)
            ->has('summary.records.damages', 1)
            ->where('summary.records.damages.0.id', $damage->id)
            ->where('summary.records.damages.0.gross', 100));
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

test('sales summary shows sale lines with customer name and phone sorted by quantity', function () {
    $this->artisan('permissions:sync');

    $date = '2026-07-01';
    $branch = Branch::factory()->create();
    $user = reportUser([ReportController::PERMISSION_SALES_SUMMARY]);
    $user->update(['branch_id' => $branch->id]);
    $customer = Customer::factory()->create([
        'branch_id' => $branch->id,
        'name' => 'Bulk Buyer',
        'phone' => '01700000001',
    ]);
    $product = Product::factory()->create(['branch_id' => $branch->id, 'name' => 'Bulk Product']);

    $sell = Sell::factory()->create([
        'branch_id' => $branch->id,
        'user_id' => $user->id,
        'customer_id' => $customer->id,
        'type' => SaleType::Sale,
        'date' => $date,
        'gross_amount' => 5000,
        'paid_amount' => 5000,
    ]);

    SellProduct::query()->create([
        'branch_id' => $branch->id,
        'sell_id' => $sell->id,
        'product_id' => $product->id,
        'quantity' => 10,
        'free_quantity' => 2,
        'unit_price' => 500,
        'discount' => 0,
        'batches' => [],
    ]);

    $smallSell = Sell::factory()->create([
        'branch_id' => $branch->id,
        'user_id' => $user->id,
        'customer_id' => $customer->id,
        'type' => SaleType::Sale,
        'date' => $date,
        'gross_amount' => 200,
        'paid_amount' => 200,
    ]);

    SellProduct::query()->create([
        'branch_id' => $branch->id,
        'sell_id' => $smallSell->id,
        'product_id' => $product->id,
        'quantity' => 2,
        'free_quantity' => 0,
        'unit_price' => 100,
        'discount' => 0,
        'batches' => [],
    ]);

    $this->actingAs($user)
        ->get('/report/sales-summary?date_from='.$date.'&date_to='.$date)
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/reports/sales-summary')
            ->has('rows', 2)
            ->where('rows.0.customer_name', 'Bulk Buyer')
            ->where('rows.0.customer_phone', '01700000001')
            ->where('rows.0.product', 'Bulk Product')
            ->where('rows.0.total_quantity', 12)
            ->where('rows.0.line_total', 5000)
            ->where('rows.1.total_quantity', 2)
            ->where('rows.1.line_total', 200)
            ->has('discount_summary', 1)
            ->where('rows.0.discount_label', 'Regular'));

    $this->actingAs($user)
        ->get('/report/sales-summary?date_from='.$date.'&date_to='.$date.'&sort=asc')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('rows.0.total_quantity', 2)
            ->where('rows.1.total_quantity', 12));
});

test('sales summary shows the same invoice number as the sell record', function () {
    $this->artisan('permissions:sync');

    $date = '2026-07-02';
    $branch = Branch::factory()->create();
    $user = reportUser([ReportController::PERMISSION_SALES_SUMMARY]);
    $user->update(['branch_id' => $branch->id]);
    $product = Product::factory()->create(['branch_id' => $branch->id, 'name' => 'Invoice Product']);

    $sell = Sell::factory()->create([
        'branch_id' => $branch->id,
        'user_id' => $user->id,
        'type' => SaleType::Sale,
        'date' => $date,
        'gross_amount' => 500,
        'paid_amount' => 500,
    ]);

    $line = SellProduct::query()->create([
        'branch_id' => $branch->id,
        'sell_id' => $sell->id,
        'product_id' => $product->id,
        'quantity' => 1,
        'free_quantity' => 0,
        'unit_price' => 500,
        'discount' => 0,
        'batches' => [],
    ]);

    $sell->refresh();

    $this->actingAs($user)
        ->get('/report/sales-summary?date_from='.$date.'&date_to='.$date)
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/reports/sales-summary')
            ->has('rows', 1)
            ->where('rows.0.id', $line->id)
            ->where('rows.0.invoice', $sell->invoice_number));
});

test('sales summary excludes deleted sales', function () {
    $this->artisan('permissions:sync');

    $date = '2026-07-03';
    $branch = Branch::factory()->create();
    $user = reportUser([ReportController::PERMISSION_SALES_SUMMARY]);
    $user->update(['branch_id' => $branch->id]);
    $product = Product::factory()->create(['branch_id' => $branch->id, 'name' => 'Deleted Sale Product']);

    $sell = Sell::factory()->create([
        'branch_id' => $branch->id,
        'user_id' => $user->id,
        'type' => SaleType::Sale,
        'date' => $date,
        'gross_amount' => 300,
        'paid_amount' => 300,
    ]);

    SellProduct::query()->create([
        'branch_id' => $branch->id,
        'sell_id' => $sell->id,
        'product_id' => $product->id,
        'quantity' => 1,
        'free_quantity' => 0,
        'unit_price' => 300,
        'discount' => 0,
        'batches' => [],
    ]);

    $this->actingAs($user)
        ->get('/report/sales-summary?date_from='.$date.'&date_to='.$date)
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->has('rows', 1));

    $sell->products()->delete();
    $sell->delete();

    $this->actingAs($user)
        ->get('/report/sales-summary?date_from='.$date.'&date_to='.$date)
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->has('rows', 0));
});

test('sales summary shows product colors and sizes for non-variant lines and variant label for variant lines', function () {
    $this->artisan('permissions:sync');

    $date = '2026-07-06';
    $branch = Branch::factory()->create();
    $user = reportUser([ReportController::PERMISSION_SALES_SUMMARY]);
    $user->update(['branch_id' => $branch->id]);

    $red = Color::query()->create(['branch_id' => $branch->id, 'name' => 'Report Red', 'status' => 1]);
    $green = Color::query()->create(['branch_id' => $branch->id, 'name' => 'Report Green', 'status' => 1]);
    $medium = Size::query()->create(['branch_id' => $branch->id, 'name' => 'Report M', 'status' => 1]);

    $plainProduct = Product::factory()->create([
        'branch_id' => $branch->id,
        'name' => 'Color Size Product',
        'colors' => [$red->id, $green->id],
        'sizes' => [$medium->id],
    ]);

    $variantProduct = Product::factory()->create([
        'branch_id' => $branch->id,
        'name' => 'Variant Product',
        'colors' => null,
        'sizes' => null,
    ]);
    $variation = ProductVariation::query()->create([
        'branch_id' => $branch->id,
        'product_id' => $variantProduct->id,
        'sku' => fake()->unique()->numerify('########'),
        'variation_data' => ['label' => 'Blue / L', 'Color' => 'Blue', 'Size' => 'L'],
        'price' => 300,
        'purchase_price' => 150,
        'stock' => 5,
        'status' => 1,
    ]);

    $sell = Sell::factory()->create([
        'branch_id' => $branch->id,
        'user_id' => $user->id,
        'type' => SaleType::Sale,
        'date' => $date,
        'gross_amount' => 800,
        'paid_amount' => 800,
    ]);

    SellProduct::query()->create([
        'branch_id' => $branch->id,
        'sell_id' => $sell->id,
        'product_id' => $plainProduct->id,
        'quantity' => 10,
        'free_quantity' => 0,
        'unit_price' => 50,
        'discount' => 0,
        'batches' => [],
    ]);

    SellProduct::query()->create([
        'branch_id' => $branch->id,
        'sell_id' => $sell->id,
        'product_id' => $variantProduct->id,
        'variation_id' => $variation->id,
        'quantity' => 3,
        'free_quantity' => 0,
        'unit_price' => 100,
        'discount' => 0,
        'batches' => [],
    ]);

    $this->actingAs($user)
        ->get('/report/sales-summary?date_from='.$date.'&date_to='.$date)
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/reports/sales-summary')
            ->has('rows', 2)
            ->where('rows.0.product', 'Color Size Product')
            ->where('rows.0.colors', ['Report Red', 'Report Green'])
            ->where('rows.0.sizes', ['Report M'])
            ->where('rows.1.product', 'Variant Product')
            ->where('rows.1.variant', 'Blue / L')
            ->where('rows.1.colors', [])
            ->where('rows.1.sizes', []));
});

test('sales summary identifies promotion discount period with highest quantity sold', function () {
    $this->artisan('permissions:sync');

    $date = '2026-07-04';
    $branch = Branch::factory()->create();
    $user = reportUser([ReportController::PERMISSION_SALES_SUMMARY]);
    $user->update(['branch_id' => $branch->id]);
    $product = Product::factory()->create(['branch_id' => $branch->id]);
    $promotion = Promotion::factory()->create([
        'branch_id' => $branch->id,
        'name' => 'July Mega Sale',
        'starts_at' => '2026-07-01 00:00:00',
        'ends_at' => '2026-07-31 23:59:59',
    ]);

    $promoSell = Sell::factory()->create([
        'branch_id' => $branch->id,
        'user_id' => $user->id,
        'type' => SaleType::Sale,
        'date' => $date,
        'gross_amount' => 3000,
        'paid_amount' => 3000,
        'promotion_discount_total' => 300,
    ]);

    SellProduct::query()->create([
        'branch_id' => $branch->id,
        'sell_id' => $promoSell->id,
        'product_id' => $product->id,
        'promotion_id' => $promotion->id,
        'quantity' => 15,
        'free_quantity' => 0,
        'unit_price' => 200,
        'promotion_discount' => 300,
        'discount' => 0,
        'batches' => [],
    ]);

    $regularSell = Sell::factory()->create([
        'branch_id' => $branch->id,
        'user_id' => $user->id,
        'type' => SaleType::Sale,
        'date' => $date,
        'gross_amount' => 500,
        'paid_amount' => 500,
    ]);

    SellProduct::query()->create([
        'branch_id' => $branch->id,
        'sell_id' => $regularSell->id,
        'product_id' => $product->id,
        'quantity' => 3,
        'free_quantity' => 0,
        'unit_price' => 500,
        'discount' => 0,
        'batches' => [],
    ]);

    $this->actingAs($user)
        ->get('/report/sales-summary?date_from='.$date.'&date_to='.$date)
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/reports/sales-summary')
            ->where('top_discount.label', 'July Mega Sale')
            ->where('top_discount.total_quantity', 15)
            ->where('discount_summary.0.key', 'promotion:'.$promotion->id)
            ->where('rows.0.discount_label', 'July Mega Sale')
            ->where('rows.0.discount_period', '2026-07-01 – 2026-07-31')
            ->where('rows.0.discount_amount', 300));

    $this->actingAs($user)
        ->get('/report/sales-summary?date_from='.$date.'&date_to='.$date.'&discount=promotion:'.$promotion->id)
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('rows', 1)
            ->where('rows.0.total_quantity', 15)
            ->where('filters.discount', 'promotion:'.$promotion->id)
            ->where('discounts', fn ($options) => collect($options)->contains(
                fn (array $option) => $option['value'] === 'promotion:'.$promotion->id
            )));
});

test('branch user sales summary excludes other branch sales', function () {
    $this->artisan('permissions:sync');

    $date = '2026-07-02';
    $branchA = Branch::factory()->create();
    $branchB = Branch::factory()->create();
    $userA = reportUser([ReportController::PERMISSION_SALES_SUMMARY]);
    $userA->update(['branch_id' => $branchA->id]);
    $product = Product::factory()->create();

    foreach ([$branchA, $branchB] as $branch) {
        $sell = Sell::factory()->create([
            'branch_id' => $branch->id,
            'user_id' => $userA->id,
            'type' => SaleType::Sale,
            'date' => $date,
            'gross_amount' => 1000,
            'paid_amount' => 1000,
        ]);

        SellProduct::query()->create([
            'branch_id' => $branch->id,
            'sell_id' => $sell->id,
            'product_id' => $product->id,
            'quantity' => 20,
            'free_quantity' => 0,
            'unit_price' => 50,
            'discount' => 0,
            'batches' => [],
        ]);
    }

    $this->actingAs($userA)
        ->get('/report/sales-summary?date_from='.$date.'&date_to='.$date)
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/reports/sales-summary')
            ->has('rows', 1)
            ->where('rows.0.quantity', 20));
});

test('sales summary filters by product and date range', function () {
    $this->artisan('permissions:sync');

    $date = '2026-07-03';
    $otherDate = '2026-06-01';
    $branch = Branch::factory()->create();
    $user = reportUser([ReportController::PERMISSION_SALES_SUMMARY]);
    $user->update(['branch_id' => $branch->id]);
    $productA = Product::factory()->create(['branch_id' => $branch->id, 'name' => 'Product A']);
    $productB = Product::factory()->create(['branch_id' => $branch->id, 'name' => 'Product B']);

    foreach ([$productA, $productB] as $product) {
        $sell = Sell::factory()->create([
            'branch_id' => $branch->id,
            'user_id' => $user->id,
            'type' => SaleType::Sale,
            'date' => $date,
            'gross_amount' => 1000,
            'paid_amount' => 1000,
        ]);

        SellProduct::query()->create([
            'branch_id' => $branch->id,
            'sell_id' => $sell->id,
            'product_id' => $product->id,
            'quantity' => 10,
            'free_quantity' => 0,
            'unit_price' => 100,
            'discount' => 0,
            'batches' => [],
        ]);
    }

    $oldSell = Sell::factory()->create([
        'branch_id' => $branch->id,
        'user_id' => $user->id,
        'type' => SaleType::Sale,
        'date' => $otherDate,
        'gross_amount' => 500,
        'paid_amount' => 500,
    ]);

    SellProduct::query()->create([
        'branch_id' => $branch->id,
        'sell_id' => $oldSell->id,
        'product_id' => $productA->id,
        'quantity' => 50,
        'free_quantity' => 0,
        'unit_price' => 10,
        'discount' => 0,
        'batches' => [],
    ]);

    $this->actingAs($user)
        ->get('/report/sales-summary?date_from='.$date.'&date_to='.$date.'&product_id='.$productA->id)
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/reports/sales-summary')
            ->where('filters.product_id', $productA->id)
            ->has('rows', 1)
            ->where('rows.0.product', 'Product A')
            ->where('rows.0.total_quantity', 10));

    $this->actingAs($user)
        ->get('/report/sales-summary?date_from='.$date.'&date_to='.$date)
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('rows', 2));
});

test('sales summary product search returns matching products', function () {
    $this->artisan('permissions:sync');

    $branch = Branch::factory()->create();
    $user = reportUser([ReportController::PERMISSION_SALES_SUMMARY]);
    $user->update(['branch_id' => $branch->id]);
    $unique = uniqid();
    $match = Product::factory()->create([
        'branch_id' => $branch->id,
        'name' => 'Searchable Widget '.$unique,
        'code' => 'SW-'.$unique,
    ]);
    Product::factory()->create([
        'branch_id' => $branch->id,
        'name' => 'Other Product '.$unique,
        'code' => 'OP-'.$unique,
    ]);

    $this->actingAs($user)
        ->getJson('/report/products/search?search='.urlencode('Searchable Widget '.$unique))
        ->assertOk()
        ->assertJsonFragment(['id' => $match->id])
        ->assertJsonMissing(['label' => 'Other Product '.$unique.' (OP-'.$unique.')']);
});

test('sales summary product search scopes to selected branch in admin panel', function () {
    $this->artisan('permissions:sync');

    $unique = uniqid();
    $branchA = Branch::factory()->create(['name' => 'Branch A '.$unique]);
    $branchB = Branch::factory()->create(['name' => 'Branch B '.$unique]);
    $admin = reportUser([ReportController::PERMISSION_SALES_SUMMARY]);
    $admin->update(['branch_id' => null]);

    $productA = Product::factory()->create([
        'branch_id' => $branchA->id,
        'name' => 'Shared Item '.$unique,
        'code' => 'SH-'.$unique,
    ]);
    Product::factory()->create([
        'branch_id' => $branchB->id,
        'name' => 'Shared Item '.$unique,
        'code' => 'SH-'.$unique,
    ]);

    $this->actingAs($admin)
        ->getJson('/report/products/search?search='.urlencode('Shared Item '.$unique).'&branch_id='.$branchA->id)
        ->assertOk()
        ->assertJsonCount(1)
        ->assertJsonFragment(['id' => $productA->id]);
});

test('sales summary product search deduplicates products across all branches', function () {
    $this->artisan('permissions:sync');

    $unique = uniqid();
    $groupId = (string) Str::uuid();
    $branchA = Branch::factory()->create();
    $branchB = Branch::factory()->create();
    $admin = reportUser([ReportController::PERMISSION_SALES_SUMMARY]);
    $admin->update(['branch_id' => null]);

    Product::factory()->create([
        'branch_id' => $branchA->id,
        'product_group_id' => $groupId,
        'name' => 'Grouped Product '.$unique,
        'code' => 'GP-'.$unique,
    ]);
    Product::factory()->create([
        'branch_id' => $branchB->id,
        'product_group_id' => $groupId,
        'name' => 'Grouped Product '.$unique,
        'code' => 'GP-'.$unique,
    ]);

    $response = $this->actingAs($admin)
        ->getJson('/report/products/search?search='.urlencode('Grouped Product '.$unique))
        ->assertOk()
        ->json();

    expect($response)->toHaveCount(1);
});

test('branch panel product search only returns own branch products', function () {
    $this->artisan('permissions:sync');

    $unique = uniqid();
    $branchA = Branch::factory()->create();
    $branchB = Branch::factory()->create();
    $branchUser = reportUser([ReportController::PERMISSION_SALES_SUMMARY]);
    $branchUser->update(['branch_id' => $branchA->id]);

    $ownProduct = Product::factory()->create([
        'branch_id' => $branchA->id,
        'name' => 'Branch Own Product '.$unique,
        'code' => 'OWN-'.$unique,
    ]);
    Product::factory()->create([
        'branch_id' => $branchB->id,
        'name' => 'Branch Own Product '.$unique,
        'code' => 'OWN-'.$unique,
    ]);

    $this->actingAs($branchUser)
        ->getJson('/report/products/search?search='.urlencode('Branch Own Product '.$unique))
        ->assertOk()
        ->assertJsonCount(1)
        ->assertJsonFragment(['id' => $ownProduct->id]);
});

test('branch stock ledger includes initial stock movements', function () {
    $this->artisan('permissions:sync');

    $branch = Branch::factory()->create();
    $user = reportUser([ReportController::PERMISSION_STOCK_LEDGER]);
    $user->update(['branch_id' => $branch->id]);

    $product = Product::factory()->create(['branch_id' => $branch->id]);
    $date = now()->format('Y-m-d');
    $batch = Batch::factory()->for($product)->withStock(0)->create(['branch_id' => $branch->id]);

    $this->travelTo($date.' 10:00:00');
    $batch->increment('available', 12);
    $batch->initialStock(12);
    $this->travelBack();

    $this->actingAs($user)
        ->get('/report/stock-ledger?product_id='.$product->id.'&date_from='.$date.'&date_to='.$date)
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('mode', 'ledger')
            ->where('product.current_stock', 12)
            ->has('entries', 1)
            ->where('entries.0.type', 'InitialStock')
            ->where('entries.0.in', 12));
});

test('daily summary includes initial stock settlements from product accounting', function () {
    $this->artisan('permissions:sync');

    Permission::findOrCreate('product.update', 'web');
    $admin = User::factory()->create(['branch_id' => null]);
    $admin->givePermissionTo('product.update');

    Branch::query()->firstOrCreate(
        ['id' => Branch::MAIN_BRANCH_ID],
        Branch::factory()->make(['name' => 'Main Branch'])->toArray(),
    );

    $cash = seedAccountingAccounts(branchId: Branch::MAIN_BRANCH_ID);
    $supplier = Supplier::factory()->create(['branch_id' => Branch::MAIN_BRANCH_ID]);
    $reportAdmin = User::factory()->create(['branch_id' => null]);
    Permission::findOrCreate(ReportController::PERMISSION_DAILY_SUMMARY, 'web');
    $reportAdmin->givePermissionTo(ReportController::PERMISSION_DAILY_SUMMARY);

    $product = Product::factory()->create([
        'branch_id' => Branch::MAIN_BRANCH_ID,
        'category_id' => Category::factory()->create(['status' => 1])->id,
        'brand_id' => Brand::factory()->create(['status' => 1])->id,
        'unit_id' => Unit::query()->create(['name' => 'Unit '.fake()->unique()->numerify('####'), 'status' => 1])->id,
        'code' => fake()->unique()->numerify('########'),
        'purchase_price' => 50,
        'sale_price' => 80,
    ]);

    $date = '2026-07-15';

    $this->travelTo($date.' 10:00:00');

    $this->actingAs($admin)
        ->patch(route('product.update', $product), [
            'category_id' => (string) $product->category_id,
            'brand_id' => (string) $product->brand_id,
            'unit_id' => (string) $product->unit_id,
            'name' => $product->name,
            'code' => $product->code,
            'purchase_price' => '50',
            'sale_price' => '80',
            'initial_stock' => '10',
            'initial_stock_supplier_id' => (string) $supplier->id,
            'initial_stock_paid_amount' => '200',
            'initial_stock_payment_account_id' => (string) $cash->id,
            'visible' => 'yes',
            'status' => '1',
        ])
        ->assertRedirect(route('product.index'));

    $this->travelBack();

    $productTransaction = Transaction::query()
        ->whereDate('date', $date)
        ->where('source_type', Product::class)
        ->where('source_id', $product->id)
        ->sole();

    expect((float) $productTransaction->amount)->toBe(500.0);

    $this->actingAs($reportAdmin)
        ->get('/report/daily-summary?date='.$date.'&branch_id='.Branch::MAIN_BRANCH_ID)
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/reports/daily-summary')
            ->where('summary.initial_stock.gross', fn ($value) => (float) $value >= 500.0)
            ->where('summary.initial_stock.paid', fn ($value) => (float) $value >= 200.0)
            ->where('summary.initial_stock.due', fn ($value) => (float) $value >= 300.0)
            ->where('summary.initial_stock.count', fn ($value) => (int) $value >= 1));
});

test('branch daily transactions include product initial stock accounting', function () {
    $this->artisan('permissions:sync');

    Permission::findOrCreate('product.update', 'web');
    $admin = User::factory()->create(['branch_id' => null]);
    $admin->givePermissionTo('product.update');

    Branch::query()->firstOrCreate(
        ['id' => Branch::MAIN_BRANCH_ID],
        Branch::factory()->make(['name' => 'Main Branch'])->toArray(),
    );

    $cash = seedAccountingAccounts(branchId: Branch::MAIN_BRANCH_ID);
    $supplier = Supplier::factory()->create(['branch_id' => Branch::MAIN_BRANCH_ID]);

    $product = Product::factory()->create([
        'branch_id' => Branch::MAIN_BRANCH_ID,
        'category_id' => Category::factory()->create(['status' => 1])->id,
        'brand_id' => Brand::factory()->create(['status' => 1])->id,
        'unit_id' => Unit::query()->create(['name' => 'Unit '.fake()->unique()->numerify('####'), 'status' => 1])->id,
        'code' => fake()->unique()->numerify('########'),
        'purchase_price' => 50,
        'sale_price' => 80,
    ]);

    $this->actingAs($admin)
        ->patch(route('product.update', $product), [
            'category_id' => (string) $product->category_id,
            'brand_id' => (string) $product->brand_id,
            'unit_id' => (string) $product->unit_id,
            'name' => $product->name,
            'code' => $product->code,
            'purchase_price' => '50',
            'sale_price' => '80',
            'initial_stock' => '10',
            'initial_stock_supplier_id' => (string) $supplier->id,
            'initial_stock_paid_amount' => '200',
            'initial_stock_payment_account_id' => (string) $cash->id,
            'visible' => 'yes',
            'status' => '1',
        ])
        ->assertRedirect(route('product.index'));

    $date = now()->format('Y-m-d');
    $mainBranchUser = User::factory()->create(['branch_id' => Branch::MAIN_BRANCH_ID]);
    $this->actingAs($mainBranchUser);

    $entries = app(ReportService::class)->dailyTransactions($date, $date);

    expect(collect($entries)->pluck('reference')->contains('Product #'.$product->id))->toBeTrue();
});

test('variant initial stock creates stock movement logs for reports', function () {
    Permission::findOrCreate('product.create', 'web');

    $admin = User::factory()->create(['branch_id' => null]);
    $admin->givePermissionTo('product.create');

    seedAccountingAccounts(branchId: Branch::MAIN_BRANCH_ID);

    Branch::query()->firstOrCreate(
        ['id' => Branch::MAIN_BRANCH_ID],
        Branch::factory()->make(['name' => 'Main Branch'])->toArray(),
    );

    $sku = fake()->unique()->numerify('########');
    $productName = 'Variant Report Stock '.fake()->unique()->numerify('######');

    $this->actingAs($admin)
        ->post(route('product.store'), [
            'branch_id' => (string) Branch::MAIN_BRANCH_ID,
            'category_id' => (string) Category::factory()->create(['status' => 1])->id,
            'brand_id' => (string) Brand::factory()->create(['status' => 1])->id,
            'unit_id' => (string) Unit::query()->create([
                'branch_id' => Branch::MAIN_BRANCH_ID,
                'name' => 'Unit '.fake()->unique()->numerify('####'),
                'status' => 1,
            ])->id,
            'name' => $productName,
            'purchase_price' => '0',
            'sale_price' => '0',
            'initial_stock' => '9',
            'visible' => 'yes',
            'status' => '1',
            'combinations' => [
                [
                    'variant' => 'Blue-L',
                    'variation_data' => ['label' => 'Blue-L', 'Color' => 'Blue', 'Size' => 'L'],
                    'sale_price' => '200',
                    'purchase_price' => '120',
                    'sku' => $sku,
                    'stock' => '',
                ],
            ],
        ])
        ->assertRedirect(route('product.index'));

    $product = Product::query()->where('name', $productName)->first();

    expect(
        ProductInOutLog::query()
            ->where('product_id', $product->id)
            ->where('type', ProductLogType::InitialStock->value)
            ->exists(),
    )->toBeTrue();

    expect(ProductVariation::query()->where('product_id', $product->id)->value('stock'))->toBe(9);
});
