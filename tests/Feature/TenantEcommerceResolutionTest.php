<?php

use App\Models\Branch;
use App\Services\EcommerceBranchService;
use App\Services\TenantDatabaseManager;
use App\Services\TenantProvisioner;
use App\Support\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

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

test('storefront middleware resolves the ecommerce tenant database', function () {
    try {
        if (! in_array(config('database.connections.'.config('database.default').'.driver'), ['mysql', 'mariadb'], true)) {
            $this->markTestSkipped('MySQL is required.');
        }
        DB::connection()->getPdo();
    } catch (Throwable) {
        $this->markTestSkipped('MySQL is required.');
    }

    config([
        'tenancy.enabled' => true,
        'tenancy.central_connection' => config('database.default'),
        'tenancy.tenant_connection' => 'tenant',
        'tenancy.database_prefix' => 'tenant_ecom_',
    ]);

    EcommerceBranchService::resetResolvedId();

    $ecommerce = Branch::query()->firstOrCreate(
        ['name' => Branch::ECOMMERCE_BRANCH_NAME],
        Branch::factory()->make(['name' => Branch::ECOMMERCE_BRANCH_NAME])->toArray(),
    );

    $provisioner = app(TenantProvisioner::class);
    $provisioner->provision($ecommerce);
    $ecommerce->refresh();

    $this->get('/')->assertSuccessful();

    expect(TenantContext::branchId())->toBe($ecommerce->id)
        ->and(config('database.connections.tenant.database'))->toBe($ecommerce->database_name);

    try {
        app(TenantDatabaseManager::class)->dropDatabase($ecommerce->database_name);
    } catch (Throwable) {
        //
    }

    $ecommerce->forceFill(['database_name' => null])->save();
    TenantContext::clear();
    EcommerceBranchService::resetResolvedId();
})->group('tenancy');
