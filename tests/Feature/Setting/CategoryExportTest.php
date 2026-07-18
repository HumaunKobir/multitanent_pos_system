<?php

use App\Models\Branch;
use App\Models\Category;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Spatie\Permission\Models\Permission;

function categoryExportAdmin(): User
{
    Artisan::call('permissions:sync');

    $user = User::factory()->create(['branch_id' => null]);
    Permission::findOrCreate('setting.category.view', 'web');
    $user->givePermissionTo('setting.category.view');

    return $user;
}

test('category index accepts date filters', function () {
    $mainBranchId = Branch::query()->firstOrCreate(
        ['name' => Branch::MAIN_BRANCH_NAME],
        Branch::factory()->make(['name' => Branch::MAIN_BRANCH_NAME])->toArray(),
    )->id;

    $admin = categoryExportAdmin();

    $matching = Category::factory()->create([
        'branch_id' => $mainBranchId,
        'status' => 1,
        'name' => 'DateFilterCat-'.uniqid(),
        'created_at' => now()->subDay(),
        'updated_at' => now()->subDay(),
    ]);

    Category::factory()->create([
        'branch_id' => $mainBranchId,
        'status' => 1,
        'name' => 'OldCat-'.uniqid(),
        'created_at' => now()->subMonths(2),
        'updated_at' => now()->subMonths(2),
    ]);

    $this->actingAs($admin)
        ->get(route('setting.category.index', [
            'date_from' => now()->subDays(3)->toDateString(),
            'date_to' => now()->toDateString(),
            'search' => $matching->name,
        ]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/setting/category/index')
            ->has('filters')
            ->where('filters.date_from', now()->subDays(3)->toDateString())
            ->has('categories.data', 1)
            ->where('categories.data.0.id', $matching->id));

    $admin->delete();
});

test('category export excel downloads', function () {
    $mainBranchId = Branch::query()->firstOrCreate(
        ['name' => Branch::MAIN_BRANCH_NAME],
        Branch::factory()->make(['name' => Branch::MAIN_BRANCH_NAME])->toArray(),
    )->id;

    $admin = categoryExportAdmin();

    Category::factory()->create([
        'branch_id' => $mainBranchId,
        'status' => 1,
        'name' => 'ExportCat-'.uniqid(),
    ]);

    $this->actingAs($admin)
        ->get(route('setting.category.export-excel'))
        ->assertOk()
        ->assertHeader('content-disposition');

    $admin->delete();
});

test('category export pdf downloads', function () {
    $admin = categoryExportAdmin();

    $this->actingAs($admin)
        ->get(route('setting.category.export-pdf'))
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf');

    $admin->delete();
});

test('category export print returns html', function () {
    $admin = categoryExportAdmin();

    $this->actingAs($admin)
        ->get(route('setting.category.export-print'))
        ->assertOk()
        ->assertSee('Categories', false);

    $admin->delete();
});
