<?php

use App\Enums\AccountType;
use App\Enums\SystemAccountKey;
use App\Models\Branch;
use App\Models\BranchSubscriptionPayment;
use App\Models\Ledger;
use App\Models\Transaction;
use App\Models\User;
use App\Services\BranchSubscriptionAccountingService;
use App\Services\BranchSubscriptionService;
use App\Services\SystemAccountService;
use App\Services\TenantDatabaseManager;
use App\Services\TenantProvisioner;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    test()->artisan('permissions:sync');

    if (
        Schema::hasTable('branch_subscription_payments')
        && ! Schema::hasColumn('branch_subscription_payments', 'payment_account_id')
    ) {
        Schema::table('branch_subscription_payments', function ($table) {
            $table->unsignedBigInteger('payment_account_id')->nullable();
        });
    }

    Branch::firstOrCreate(['id' => Branch::MAIN_BRANCH_ID], [
        'name' => Branch::MAIN_BRANCH_NAME,
        'status' => 1,
        'subscription_status' => 'lifetime',
    ]);

    SystemAccountService::seed(null);
});

test('system accounts for subscription exist on branch and superadmin charts', function () {
    $branch = Branch::factory()->create();
    SystemAccountService::seed($branch->id);

    // Branch panel has subscription payable and subscription expense
    $branchPayable = SystemAccountService::resolve(SystemAccountKey::SubscriptionPayable, $branch->id);
    $branchExpense = SystemAccountService::resolve(SystemAccountKey::SubscriptionExpense, $branch->id);

    expect($branchPayable)->not->toBeNull();
    expect($branchPayable->type->value)->toBe(AccountType::Liability->value);
    expect($branchPayable->source_type)->toBe(Branch::class);
    expect((int) $branchPayable->source_id)->toBe($branch->id);

    expect($branchExpense)->not->toBeNull();
    expect($branchExpense->type->value)->toBe(AccountType::Expenses->value);
    expect($branchExpense->source_type)->toBe(Branch::class);
    expect((int) $branchExpense->source_id)->toBe($branch->id);

    // SuperAdmin global panel has subscription income
    $superadminIncome = SystemAccountService::resolve(SystemAccountKey::SubscriptionIncome, null);
    expect($superadminIncome)->not->toBeNull();
    expect($superadminIncome->type->value)->toBe(AccountType::Income->value);
    expect($superadminIncome->source_type)->toBeNull();
    expect($superadminIncome->source_id)->toBeNull();
});

test('cycle reached does not post unapproved GL entries', function () {
    $branch = Branch::factory()->create([
        'name' => 'Branch Khulna',
        'subscription_fee' => 2000,
        'subscription_starts_at' => '2026-09-01',
        'subscription_expires_at' => '2026-10-01',
    ]);

    SystemAccountService::seed($branch->id);

    $accountingService = app(BranchSubscriptionAccountingService::class);
    $accrual = $accountingService->recordCycleAccrual($branch, '2026-09-01', '2026-10-01', 2000);

    // Unapproved cycle accrual returns null
    expect($accrual['branch_accrual'])->toBeNull()
        ->and($accrual['superadmin_accrual'])->toBeNull();

    $payable = SystemAccountService::resolve(SystemAccountKey::SubscriptionPayable, $branch->id);
    $expense = SystemAccountService::resolve(SystemAccountKey::SubscriptionExpense, $branch->id);

    // No GL balances changed before payment approval
    expect((float) $payable->fresh()->current_balance)->toBe(0.0)
        ->and((float) $expense->fresh()->current_balance)->toBe(0.0);
});

test('superadmin approving payment records superadmin income and cash and client expense and cash', function () {
    Storage::fake('public');

    $branch = Branch::factory()->create([
        'name' => 'Branch Barisal',
        'subscription_fee' => 1500,
        'subscription_starts_at' => '2026-09-01',
        'subscription_expires_at' => '2026-10-01',
    ]);

    SystemAccountService::seed(null);
    SystemAccountService::seed($branch->id);

    $superadminIncome = SystemAccountService::resolve(SystemAccountKey::SubscriptionIncome, null);
    $superadminBkash = SystemAccountService::resolve(SystemAccountKey::Bkash, null);

    $initialSuperadminIncome = (float) $superadminIncome->fresh()->current_balance;
    $initialSuperadminCash = (float) $superadminBkash->fresh()->current_balance;

    $branchBkash = SystemAccountService::resolve(SystemAccountKey::Bkash, $branch->id);

    // 1. Client submits payment (pending)
    $clientUser = User::factory()->create(['branch_id' => $branch->id]);
    $receipt = UploadedFile::fake()->image('bkash_receipt.png');

    $this->actingAs($clientUser)->post(route('branch-panel.subscription.pay'), [
        'duration_days' => 30,
        'amount' => 1500,
        'payment_method' => 'bKash',
        'payment_account_id' => $branchBkash->id,
        'transaction_reference' => 'BKASH-ACC-TRX-1234',
        'paid_at' => '2026-09-09',
        'notes' => 'Subscription monthly renewal',
        'attachment' => $receipt,
    ]);

    $pendingPayment = BranchSubscriptionPayment::where('branch_id', $branch->id)->latest('id')->first();
    expect($pendingPayment->status)->toBe('pending')
        ->and((int) $pendingPayment->payment_account_id)->toBe($branchBkash->id);

    // 2. SuperAdmin approves payment
    $admin = User::factory()->create(['branch_id' => null]);
    $admin->givePermissionTo(['branch.view', 'branch.update']);

    $this->actingAs($admin)->post(route('branch-clients.renew', $branch->id), [
        'pending_payment_id' => $pendingPayment->id,
        'duration_days' => 30,
        'amount' => 1500,
        'payment_method' => 'bkash',
        'transaction_reference' => 'BKASH-ACC-TRX-1234',
        'paid_at' => '2026-09-09',
        'notes' => 'Verified deposit on bKash merchant account',
        'existing_attachment_path' => $pendingPayment->attachment_path,
    ])->assertRedirect();

    // 3. Verify Branch Ledger upon approval:
    // Expense: Subscription Expense increased by 1500
    // Asset: Branch bKash decreased by 1500
    $branchExpense = SystemAccountService::resolve(SystemAccountKey::SubscriptionExpense, $branch->id);
    expect((float) $branchExpense->fresh()->current_balance)->toBe(1500.0);
    expect((float) $branchBkash->fresh()->current_balance)->toBe(-1500.0);

    // 4. Verify SuperAdmin Ledger upon approval:
    // Income: Subscription Income increased by 1500
    // Cash Asset (bKash) increased by 1500
    expect((float) $superadminIncome->fresh()->current_balance)->toBe($initialSuperadminIncome + 1500.0);
    expect((float) $superadminBkash->fresh()->current_balance)->toBe($initialSuperadminCash + 1500.0);

    // 5. Verify Transaction entries exist
    $branchSettlementTx = Transaction::query()
        ->where('source_type', BranchSubscriptionPayment::class)
        ->where('source_id', $pendingPayment->id)
        ->where('debit_account_id', $branchExpense->id)
        ->first();

    expect($branchSettlementTx)->not->toBeNull();
    expect((float) $branchSettlementTx->amount)->toBe(1500.0);

    $superadminSettlementTx = Transaction::query()
        ->where('source_type', BranchSubscriptionPayment::class)
        ->where('source_id', $pendingPayment->id)
        ->where('credit_account_id', $superadminIncome->id)
        ->first();

    expect($superadminSettlementTx)->not->toBeNull();
    expect((float) $superadminSettlementTx->amount)->toBe(1500.0);
});

test('superadmin direct renewal posts expense on client and income on superadmin', function () {
    $branch = Branch::factory()->create([
        'name' => 'Branch Sylhet',
        'subscription_fee' => 2500,
        'subscription_starts_at' => '2026-09-01',
        'subscription_expires_at' => '2026-10-01',
    ]);

    SystemAccountService::seed(null);
    SystemAccountService::seed($branch->id);

    $admin = User::factory()->create(['branch_id' => null]);
    $admin->givePermissionTo(['branch.view', 'branch.update']);

    $initialSuperadminIncome = (float) SystemAccountService::resolve(SystemAccountKey::SubscriptionIncome, null)->fresh()->current_balance;
    $initialSuperadminNagad = (float) SystemAccountService::resolve(SystemAccountKey::Nagad, null)->fresh()->current_balance;

    // Direct renewal by SuperAdmin
    $this->actingAs($admin)->post(route('branch-clients.renew', $branch->id), [
        'duration_days' => 30,
        'amount' => 2500,
        'payment_method' => 'Nagad',
        'transaction_reference' => 'NAGAD-TRX-DIRECT-999',
        'paid_at' => '2026-09-09',
        'notes' => 'Direct counter renewal by SuperAdmin',
    ])->assertRedirect();

    $branchExpense = SystemAccountService::resolve(SystemAccountKey::SubscriptionExpense, $branch->id);
    $branchNagad = SystemAccountService::resolve(SystemAccountKey::Nagad, $branch->id);
    $superadminIncome = SystemAccountService::resolve(SystemAccountKey::SubscriptionIncome, null);
    $superadminNagad = SystemAccountService::resolve(SystemAccountKey::Nagad, null);

    // 1. Client Branch Expense increased by 2500
    expect((float) $branchExpense->fresh()->current_balance)->toBe(2500.0);

    // 2. Client Branch Asset decreased by 2500
    expect((float) $branchNagad->fresh()->current_balance)->toBe(-2500.0);

    // 3. SuperAdmin Income increased by 2500
    expect((float) $superadminIncome->fresh()->current_balance)->toBe($initialSuperadminIncome + 2500.0);

    // 4. SuperAdmin Asset increased by 2500
    expect((float) $superadminNagad->fresh()->current_balance)->toBe($initialSuperadminNagad + 2500.0);
});

test('overdue summary tracks pending bills count and overdue fee without posting unapproved GL entries', function () {
    $this->travelTo('2026-09-09');

    $branch = Branch::factory()->create([
        'name' => 'Branch Overdue Payable',
        'subscription_plan' => 'custom_days',
        'custom_cycle_days' => 2,
        'subscription_fee' => 1500,
        'subscription_status' => 'active',
        'subscription_starts_at' => '2026-09-01',
        'subscription_expires_at' => '2026-09-03',
        'custom_overdue_action' => 'none',
        'custom_grace_period_days' => 0,
    ]);

    SystemAccountService::seed(null);
    SystemAccountService::seed($branch->id);

    $summary = app(BranchSubscriptionService::class)->getSubscriptionSummary($branch);

    expect($summary['is_overdue'])->toBeTrue()
        ->and($summary['pending_bills_count'])->toBe(3)
        ->and((float) $summary['total_overdue_fee'])->toBe(4500.0);

    // Without approval: No GL posting to SubscriptionPayable or SubscriptionExpense
    $payable = SystemAccountService::resolve(SystemAccountKey::SubscriptionPayable, $branch->id);
    $expense = SystemAccountService::resolve(SystemAccountKey::SubscriptionExpense, $branch->id);

    expect((float) $payable->fresh()->current_balance)->toBe(0.0)
        ->and((float) $expense->fresh()->current_balance)->toBe(0.0);
});

test('branch accounts page does not post unapproved subscription dues', function () {
    $this->travelTo('2026-09-09');
    $this->artisan('permissions:sync');

    $branch = Branch::factory()->create([
        'name' => 'Branch Accounts Accrual',
        'subscription_plan' => 'custom_days',
        'custom_cycle_days' => 2,
        'subscription_fee' => 1500,
        'subscription_status' => 'active',
        'subscription_starts_at' => '2026-09-01',
        'subscription_expires_at' => '2026-09-03',
        'custom_overdue_action' => 'none',
        'custom_grace_period_days' => 0,
    ]);

    SystemAccountService::seed(null);
    SystemAccountService::seed($branch->id);

    $user = User::factory()->create(['branch_id' => $branch->id]);
    $user->givePermissionTo('accounts.view');

    $payable = SystemAccountService::resolve(SystemAccountKey::SubscriptionPayable, $branch->id);
    expect((float) $payable->fresh()->current_balance)->toBe(0.0);

    $this->actingAs($user)
        ->get(route('accounts.index'))
        ->assertOk();

    // Remains 0 without payment approval
    expect((float) $payable->fresh()->current_balance)->toBe(0.0);
});

test('payment account key maps rocket and bank to bank account', function () {
    $service = app(BranchSubscriptionAccountingService::class);

    expect($service->resolvePaymentAccountKey('Rocket')->value)->toBe(SystemAccountKey::BankAccount->value)
        ->and($service->resolvePaymentAccountKey('Bank Transfer')->value)->toBe(SystemAccountKey::BankAccount->value)
        ->and($service->resolvePaymentAccountKey('bkash')->value)->toBe(SystemAccountKey::Bkash->value);
});

test('settlement credits the selected payment_account_id on the client branch', function () {
    $branch = Branch::factory()->create([
        'name' => 'Branch Channel Pick',
        'subscription_fee' => 1000,
        'subscription_starts_at' => '2026-09-01',
        'subscription_expires_at' => '2026-10-01',
    ]);

    SystemAccountService::seed(null);
    SystemAccountService::seed($branch->id);

    $cash = SystemAccountService::resolve(SystemAccountKey::CashInHand, $branch->id);
    $nagad = SystemAccountService::resolve(SystemAccountKey::Nagad, $branch->id);

    $payment = BranchSubscriptionPayment::create([
        'branch_id' => $branch->id,
        'amount' => 1000,
        'payment_method' => 'Cash in Hand',
        'payment_account_id' => $cash->id,
        'status' => 'approved',
        'transaction_reference' => 'CASH-SEL-1',
        'billing_period_starts_at' => '2026-10-01',
        'billing_period_ends_at' => '2026-10-31',
        'paid_at' => '2026-09-09',
    ]);

    app(BranchSubscriptionAccountingService::class)->recordPaymentSettlement($payment);

    expect((float) $cash->fresh()->current_balance)->toBe(-1000.0)
        ->and((float) $nagad->fresh()->current_balance)->toBe(0.0)
        ->and((float) SystemAccountService::resolve(SystemAccountKey::SubscriptionExpense, $branch->id)->fresh()->current_balance)->toBe(1000.0);
});

test('tenant aware settlement posts client transactions on the branch tenant database', function () {
    try {
        if (! in_array(config('database.connections.'.config('database.default').'.driver'), ['mysql', 'mariadb'], true)) {
            $this->markTestSkipped('MySQL is required for tenant subscription posting.');
        }
        DB::connection()->getPdo();
    } catch (Throwable) {
        $this->markTestSkipped('MySQL is required for tenant subscription posting.');
    }

    if (! Schema::hasColumn('branches', 'database_name')) {
        Schema::table('branches', function ($table) {
            $table->string('database_name', 64)->nullable()->unique();
        });
    }

    config([
        'tenancy.enabled' => true,
        'tenancy.central_connection' => config('database.default'),
        'tenancy.tenant_connection' => 'tenant',
        'tenancy.database_prefix' => 'tenant_sub_',
    ]);

    $provisioner = app(TenantProvisioner::class);
    $manager = app(TenantDatabaseManager::class);

    $main = Branch::query()->firstOrCreate(
        ['name' => Branch::MAIN_BRANCH_NAME],
        Branch::factory()->make(['name' => Branch::MAIN_BRANCH_NAME, 'subscription_status' => 'lifetime'])->toArray(),
    );
    $branch = Branch::factory()->create([
        'name' => 'Sub Tenant '.fake()->unique()->numerify('####'),
        'subscription_fee' => 800,
        'subscription_starts_at' => '2026-09-01',
        'subscription_expires_at' => '2026-10-01',
    ]);

    try {
        $provisioner->provision($main);
        $provisioner->provision($branch);
        $main->refresh();
        $branch->refresh();

        $provisioner->usingBranch($main, fn () => SystemAccountService::seed(null));
        $cashId = $provisioner->usingBranch($branch, function () use ($branch) {
            SystemAccountService::seed($branch->id);

            return SystemAccountService::resolve(SystemAccountKey::CashInHand, $branch->id)->id;
        });

        $payment = BranchSubscriptionPayment::create([
            'branch_id' => $branch->id,
            'amount' => 800,
            'payment_method' => 'Cash in Hand',
            'payment_account_id' => $cashId,
            'status' => 'approved',
            'transaction_reference' => 'TENANT-SUB-800',
            'billing_period_starts_at' => '2026-10-01',
            'billing_period_ends_at' => '2026-10-31',
            'paid_at' => '2026-09-09',
        ]);

        app(BranchSubscriptionAccountingService::class)->recordPaymentSettlement($payment);

        $clientPosted = $provisioner->usingBranch($branch, function () use ($payment, $branch) {
            $expense = (float) SystemAccountService::resolve(SystemAccountKey::SubscriptionExpense, $branch->id)->fresh()->current_balance;
            $cash = (float) SystemAccountService::resolve(SystemAccountKey::CashInHand, $branch->id)->fresh()->current_balance;
            $settlementExists = Transaction::query()
                ->where('source_type', BranchSubscriptionPayment::class)
                ->where('source_id', $payment->id)
                ->exists();

            return compact('expense', 'cash', 'settlementExists');
        });

        expect($clientPosted['settlementExists'])->toBeTrue()
            ->and($clientPosted['expense'])->toBe(800.0)
            ->and($clientPosted['cash'])->toBe(-800.0);
    } finally {
        foreach ([$branch, $main] as $item) {
            if ($item->name === Branch::MAIN_BRANCH_NAME) {
                $item->forceFill(['database_name' => null])->save();

                continue;
            }

            if (filled($item->database_name)) {
                try {
                    $manager->dropDatabase($item->database_name);
                } catch (Throwable) {
                    //
                }
            }

            $item->delete();
        }

        config(['tenancy.enabled' => false]);
        DB::purge('tenant');
    }
});
