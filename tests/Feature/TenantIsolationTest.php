<?php

use App\Models\Branch;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use App\Services\TenantDatabaseManager;
use App\Services\TenantProvisioner;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

function isolationMysqlAvailable(): bool
{
    try {
        if (! in_array(config('database.connections.'.config('database.default').'.driver'), ['mysql', 'mariadb'], true)) {
            return false;
        }

        DB::connection()->getPdo();

        return true;
    } catch (Throwable) {
        return false;
    }
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

test('products written in one tenant database are not visible in another', function () {
    if (! isolationMysqlAvailable()) {
        $this->markTestSkipped('MySQL is required for tenant isolation tests.');
    }

    config([
        'tenancy.enabled' => true,
        'tenancy.central_connection' => config('database.default'),
        'tenancy.tenant_connection' => 'tenant',
        'tenancy.database_prefix' => 'tenant_iso_',
    ]);

    $provisioner = app(TenantProvisioner::class);
    $manager = app(TenantDatabaseManager::class);

    $branchA = Branch::factory()->create(['name' => 'Iso A '.fake()->unique()->numerify('####')]);
    $branchB = Branch::factory()->create(['name' => 'Iso B '.fake()->unique()->numerify('####')]);

    $provisioner->provision($branchA);
    $provisioner->provision($branchB);
    $branchA->refresh();
    $branchB->refresh();

    $provisioner->usingBranch($branchA, function () use ($branchA): void {
        $category = Category::factory()->create([
            'branch_id' => $branchA->id,
            'status' => 1,
        ]);

        Product::factory()->create([
            'branch_id' => $branchA->id,
            'category_id' => $category->id,
            'name' => 'Only In A',
            'code' => 'ISO-A-'.fake()->unique()->numerify('######'),
        ]);
    });

    $countInB = $provisioner->usingBranch($branchB, fn (): int => Product::query()->where('name', 'Only In A')->count());
    $countInA = $provisioner->usingBranch($branchA, fn (): int => Product::query()->where('name', 'Only In A')->count());

    expect($countInA)->toBe(1)
        ->and($countInB)->toBe(0);

    foreach ([$branchA, $branchB] as $branch) {
        try {
            $manager->dropDatabase($branch->database_name);
        } catch (Throwable) {
            //
        }
        $branch->delete();
    }
})->group('tenancy');

test('central users remain on the central connection', function () {
    config(['tenancy.enabled' => true]);

    $user = User::factory()->create();

    expect($user->getConnectionName())->toBe(config('tenancy.central_connection'))
        ->and((new Product)->getConnectionName())->toBe(config('tenancy.tenant_connection'));
})->group('tenancy');
