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

        $branch = Branch::query()->create([
            'name' => 'Main Branch',
            'phone' => '01700000000',
            'address' => 'ঢাকা, বাংলাদেশ',
            'status' => CommonStatus::Active,
        ]);

        User::query()->create([
            'name' => 'Branch Admin',
            'email' => 'branchadmin@coolness.com',
            'phone' => '01700000002',
            'password' => bcrypt('123456789'),
            'branch_id' => $branch->id,
            'status' => 1,
        ]);
    }
}
