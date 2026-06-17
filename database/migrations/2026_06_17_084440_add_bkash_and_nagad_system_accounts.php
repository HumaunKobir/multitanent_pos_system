<?php

use App\Services\SystemAccountService;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        SystemAccountService::seed();
    }

    public function down(): void
    {
        // System account structure is forward-only; no rollback.
    }
};
