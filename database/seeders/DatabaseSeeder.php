<?php

namespace Database\Seeders;

use App\Enums\CommonStatus;
use App\Models\Branch;
use App\Models\User;
use App\Services\TenantProvisioner;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $mainBranch = Branch::query()->create([
            'name' => Branch::MAIN_BRANCH_NAME,
            'phone' => '01700000000',
            'address' => 'ঢাকা, বাংলাদেশ',
            'status' => CommonStatus::Active,
        ]);

        User::query()->create([
            'name' => 'Super Admin',
            'email' => 'superadmin@gmail.com',
            'phone' => '01700000001',
            'password' => bcrypt('123456789'),
            'branch_id' => $mainBranch->id,
            'status' => CommonStatus::Active,
        ]);


        $ecommerceBranch = Branch::query()->create([
            'name' => Branch::ECOMMERCE_BRANCH_NAME,
            'phone' => '01700000001',
            'address' => 'ঢাকা, বাংলাদেশ',
            'status' => CommonStatus::Active,
        ]);

        if (config('tenancy.enabled')) {
            app(TenantProvisioner::class)->provision($mainBranch);
            app(TenantProvisioner::class)->provision($ecommerceBranch);
        } else {
            $this->call([
                ChartOfAccountsSeeder::class,
                // DemoCatalogSeeder::class,
            ]);
        }
    }
}
