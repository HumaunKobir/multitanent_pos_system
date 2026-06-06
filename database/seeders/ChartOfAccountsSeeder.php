<?php

namespace Database\Seeders;

use App\Services\SystemAccountService;
use Illuminate\Database\Seeder;

class ChartOfAccountsSeeder extends Seeder
{
    public function run(): void
    {
        SystemAccountService::seed();
    }
}
