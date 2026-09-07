<?php

use App\Models\Batch;
use App\Models\Branch;
use App\Models\Category;
use App\Models\Product;
use App\Services\StockDistributionService;
use App\Services\TenantDatabaseManager;
use App\Services\TenantProvisioner;
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

test('stock distribution credits destination tenant database when tenancy is enabled', function () {
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
        'tenancy.database_prefix' => 'tenant_dist_',
    ]);

    $provisioner = app(TenantProvisioner::class);
    $manager = app(TenantDatabaseManager::class);

    $main = Branch::query()->firstOrCreate(
        ['name' => Branch::MAIN_BRANCH_NAME],
        Branch::factory()->make(['name' => Branch::MAIN_BRANCH_NAME])->toArray(),
    );
    $destination = Branch::factory()->create([
        'name' => 'Dist Dest '.fake()->unique()->numerify('####'),
    ]);

    $provisioner->provision($main);
    $provisioner->provision($destination);
    $main->refresh();
    $destination->refresh();

    $product = $provisioner->usingBranch($main, function () use ($main) {
        $category = Category::factory()->create([
            'branch_id' => $main->id,
            'status' => 1,
        ]);

        $product = Product::factory()->create([
            'branch_id' => $main->id,
            'category_id' => $category->id,
            'name' => 'Dist Product',
            'code' => 'DIST-'.fake()->unique()->numerify('######'),
        ]);

        Batch::query()->create([
            'branch_id' => $main->id,
            'product_id' => $product->id,
            'purchase_price' => 10,
            'available' => 5,
            'serial' => null,
            'expiry_date' => null,
        ]);

        return $product;
    });

    $service = app(StockDistributionService::class);

    $distribution = $service->createPendingDistribution([
        'to_branch_id' => $destination->id,
        'date' => now()->toDateString(),
        'comment' => 'dual db',
        'items' => [
            [
                'product_id' => $product->id,
                'variation_id' => null,
                'quantity' => 2,
            ],
        ],
    ], $main->id);

    expect($distribution->products)->toHaveCount(1);

    $service->receiveLines($distribution, [$distribution->products->first()->id], 1);

    $destStock = $provisioner->usingBranch($destination, function () use ($destination) {
        return (float) Batch::query()->where('branch_id', $destination->id)->sum('available');
    });

    expect($destStock)->toBe(2.0);

    foreach ([$main, $destination] as $branch) {
        if ($branch->name === Branch::MAIN_BRANCH_NAME) {
            $branch->forceFill(['database_name' => null])->save();

            continue;
        }

        try {
            $manager->dropDatabase($branch->database_name);
        } catch (Throwable) {
            //
        }
        $branch->delete();
    }
})->group('tenancy');
