<?php

use App\Enums\AccountType;
use App\Enums\CommonStatus;
use App\Enums\SystemAccountKey;
use App\Enums\VoucherType;
use App\Models\Branch;
use App\Models\ChartOfAccount;
use App\Models\Customer;
use App\Models\User;
use App\Models\Voucher;
use App\Services\SystemAccountService;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;

function voucherShowUser(array $permissions = []): User
{
    $branch = Branch::factory()->create();
    $user = User::factory()->create(['branch_id' => $branch->id]);

    foreach ($permissions as $permission) {
        Permission::findOrCreate($permission, 'web');
        $user->givePermissionTo($permission);
    }

    return $user;
}

test('voucher show page renders inertia detail view', function () {
    $this->artisan('permissions:sync');

    $user = voucherShowUser(['accounts.view', 'accounts.create']);
    $cash = seedAccountingAccounts(branchId: $user->branch_id);

    ChartOfAccount::$skipCodeGeneration = true;
    $incomeAccount = ChartOfAccount::query()->firstOrCreate(
        [
            'code' => 'TEST-INC-SHOW',
            'source_type' => Branch::class,
            'source_id' => $user->branch_id,
        ],
        [
            'parent_id' => SystemAccountService::id(SystemAccountKey::SalesRevenue, $user->branch_id),
            'name' => 'Show Income Head',
            'type' => AccountType::Income,
            'status' => CommonStatus::Active,
            'current_balance' => 0,
        ],
    );
    ChartOfAccount::$skipCodeGeneration = false;

    $customer = Customer::factory()->create([
        'branch_id' => $user->branch_id,
        'name' => 'Receipt Party',
        'email' => 'party@example.com',
    ]);

    $this->actingAs($user)
        ->post('/accounts/vouchers', [
            'type' => VoucherType::Income->value,
            'voucher_no' => 'INC-SHOW-'.fake()->unique()->numerify('####'),
            'date' => '2026-07-22',
            'party_key' => "customer:{$customer->id}",
            'payment_account_id' => $cash->id,
            'narration' => 'test voucher',
            'lines' => [
                [
                    'account_id' => $incomeAccount->id,
                    'amount' => 650,
                    'narration' => null,
                ],
            ],
        ])
        ->assertRedirect();

    $voucher = Voucher::query()->latest('id')->firstOrFail();

    $this->actingAs($user)
        ->get(route('accounts.vouchers.show', $voucher))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/accounts/vouchers/show')
            ->where('voucher.id', $voucher->id)
            ->where('voucher.voucher_no', $voucher->voucher_no)
            ->where('voucher.party_name', 'Receipt Party')
            ->where('voucher.party_email', 'party@example.com')
            ->where('voucher.total_amount', 650)
            ->where('voucher.status', 'Active')
            ->has('voucher.lines', 2));
});

test('voucher show still returns json for edit modal requests', function () {
    $this->artisan('permissions:sync');

    $user = voucherShowUser(['accounts.view', 'accounts.create']);
    $cash = seedAccountingAccounts(branchId: $user->branch_id);

    ChartOfAccount::$skipCodeGeneration = true;
    $incomeAccount = ChartOfAccount::query()->firstOrCreate(
        [
            'code' => 'TEST-INC-JSON',
            'source_type' => Branch::class,
            'source_id' => $user->branch_id,
        ],
        [
            'parent_id' => SystemAccountService::id(SystemAccountKey::SalesRevenue, $user->branch_id),
            'name' => 'Json Income Head',
            'type' => AccountType::Income,
            'status' => CommonStatus::Active,
            'current_balance' => 0,
        ],
    );
    ChartOfAccount::$skipCodeGeneration = false;

    $this->actingAs($user)
        ->post('/accounts/vouchers', [
            'type' => VoucherType::Income->value,
            'voucher_no' => 'INC-JSON-'.fake()->unique()->numerify('####'),
            'date' => '2026-07-22',
            'payment_account_id' => $cash->id,
            'lines' => [
                [
                    'account_id' => $incomeAccount->id,
                    'amount' => 100,
                ],
            ],
        ])
        ->assertRedirect();

    $voucher = Voucher::query()->latest('id')->firstOrFail();

    $this->actingAs($user)
        ->getJson(route('accounts.vouchers.show', $voucher))
        ->assertOk()
        ->assertJsonPath('voucher.id', $voucher->id)
        ->assertJsonPath('voucher.voucher_no', $voucher->voucher_no);
});
