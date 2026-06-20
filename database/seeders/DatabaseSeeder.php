<?php

namespace Database\Seeders;

use App\Enums\CommonStatus;
use App\Models\Branch;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        User::query()->create([
            'name' => 'Super Admin',
            'email' => 'superadmin@coolness.com',
            'phone' => '01700000001',
            'password' => bcrypt('123456789'),
            'branch_id' => null,
            'status' => 1,
        ]);

        $mainBranch = Branch::query()->create([
            'name' => Branch::MAIN_BRANCH_NAME,
            'phone' => '01700000000',
            'address' => 'ঢাকা, বাংলাদেশ',
            'status' => CommonStatus::Active,
        ]);

        $ecommerceBranch = Branch::query()->create([
            'name' => Branch::ECOMMERCE_BRANCH_NAME,
            'phone' => '01700000001',
            'address' => 'ঢাকা, বাংলাদেশ',
            'status' => CommonStatus::Active,
        ]);

        User::query()->create([
            'name' => 'Branch Admin',
            'email' => User::ECOMMERCE_BRANCH_ADMIN_EMAIL,
            'phone' => '01700000002',
            'password' => bcrypt('123456789'),
            'branch_id' => $ecommerceBranch->id,
            'status' => 1,
        ]);

        $this->call([
            ChartOfAccountsSeeder::class,
            // DemoCatalogSeeder::class,
        ]);
    }
}
