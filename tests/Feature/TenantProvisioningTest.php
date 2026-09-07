<?php

use App\Models\Branch;
use App\Models\User;
use App\Services\TenantDatabaseManager;
use App\Services\TenantProvisioner;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

function tenancyMysqlAvailable(): bool
{
    try {
        $driver = config('database.connections.'.config('database.default').'.driver');

        if (! in_array($driver, ['mysql', 'mariadb'], true)) {
            return false;
        }

        DB::connection()->getPdo();

        return true;
    } catch (Throwable) {
        return false;
    }
}

function enableTenancyForTest(): void
{
    config([
        'tenancy.enabled' => true,
        'tenancy.central_connection' => config('database.default'),
        'tenancy.tenant_connection' => 'tenant',
        'tenancy.database_prefix' => 'tenant_test_',
    ]);
}

function cleanupTenantBranch(Branch $branch): void
{
    if (filled($branch->database_name)) {
        try {
            app(TenantDatabaseManager::class)->dropDatabase($branch->database_name);
        } catch (Throwable) {
            //
        }
    }

    $branch->delete();
}

beforeEach(function () {
    if (! Schema::hasColumn('branches', 'database_name')) {
        Schema::table('branches', function ($table) {
            $table->string('database_name', 64)->nullable()->unique();
        });
    }

    Branch::query()
        ->whereNotNull('database_name')
        ->where('database_name', 'like', 'tenant_%')
        ->update(['database_name' => null]);

    config([
        'database.connections.tenant.database' => config('database.connections.'.config('database.default').'.database'),
    ]);
    DB::purge('tenant');
});

test('creating a branch provisions a tenant database when tenancy is enabled', function () {
    if (! tenancyMysqlAvailable()) {
        $this->markTestSkipped('MySQL is required for tenant provisioning tests.');
    }

    enableTenancyForTest();

    $admin = User::factory()->create(['branch_id' => null]);
    $name = 'Prov '.fake()->unique()->numerify('####');

    $this->actingAs($admin)
        ->post(route('branch.store'), [
            'name' => $name,
            'phone' => '01700000001',
            'address' => 'Test address',
        ])
        ->assertRedirect(route('branch.index'));

    $branch = Branch::query()->where('name', $name)->first();

    expect($branch)->not->toBeNull()
        ->and($branch->database_name)->not->toBeNull()
        ->and($branch->database_name)->toStartWith('tenant_test_');

    app(TenantDatabaseManager::class)->configureTenantConnection($branch->database_name);

    expect(Schema::connection('tenant')->hasTable('products'))->toBeTrue()
        ->and(Schema::connection('tenant')->hasTable('chart_of_accounts'))->toBeTrue();

    cleanupTenantBranch($branch);
})->group('tenancy');

test('tenant provisioner initializes branch connection', function () {
    if (! tenancyMysqlAvailable()) {
        $this->markTestSkipped('MySQL is required for tenant provisioning tests.');
    }

    enableTenancyForTest();

    $branch = Branch::factory()->create([
        'name' => 'Init '.fake()->unique()->numerify('####'),
    ]);

    $provisioned = app(TenantProvisioner::class)->provision($branch);

    expect($provisioned->database_name)->not->toBeEmpty();

    app(TenantProvisioner::class)->initialize($provisioned->fresh());

    expect(config('database.connections.tenant.database'))->toBe($provisioned->database_name);

    cleanupTenantBranch($provisioned->fresh());
})->group('tenancy');
