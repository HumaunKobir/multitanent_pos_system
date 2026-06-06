<?php

use App\Models\Branch;
use App\Services\EcommerceBranchService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contacts', function (Blueprint $table) {
            $table->foreignId('branch_id')
                ->nullable()
                ->after('id')
                ->constrained()
                ->nullOnDelete();
        });

        $branchId = Branch::query()
            ->where('name', EcommerceBranchService::BRANCH_NAME)
            ->value('id') ?? Branch::MAIN_BRANCH_ID;

        if ($branchId !== null) {
            DB::table('contacts')->whereNull('branch_id')->update(['branch_id' => $branchId]);
        }
    }

    public function down(): void
    {
        Schema::table('contacts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('branch_id');
        });
    }
};
