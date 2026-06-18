<?php

use App\Models\Branch;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * @var list<string>
     */
    private array $tables = [
        'categories',
        'brands',
        'units',
        'warranties',
        'colors',
        'sizes',
        'tags',
    ];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        foreach ($this->tables as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint) use ($table): void {
                if (! Schema::hasColumn($table, 'branch_id')) {
                    $blueprint->unsignedBigInteger('branch_id')->nullable()->after('id');
                    $blueprint->index('branch_id');
                }

                if (! Schema::hasColumn($table, 'catalog_group_id')) {
                    $blueprint->uuid('catalog_group_id')->nullable()->after('branch_id');
                    $blueprint->index('catalog_group_id');
                }
            });
        }

        $this->ensureForeignKeys();

        if (! Schema::hasTable('branches')) {
            return;
        }

        $defaultBranchId = Branch::resolveAdminCatalogBranchId();

        foreach ($this->tables as $table) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'branch_id')) {
                continue;
            }

            DB::table($table)->whereNull('branch_id')->update([
                'branch_id' => $defaultBranchId,
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Intentionally left empty: this migration only ensures missing columns exist.
    }

    private function ensureForeignKeys(): void
    {
        if (! Schema::hasTable('branches')) {
            return;
        }

        foreach ($this->tables as $table) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'branch_id')) {
                continue;
            }

            $constraintName = "{$table}_branch_id_foreign";

            $exists = collect(DB::select(
                'SELECT CONSTRAINT_NAME FROM information_schema.TABLE_CONSTRAINTS
                 WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND CONSTRAINT_NAME = ?',
                [DB::getDatabaseName(), $table, $constraintName],
            ))->isNotEmpty();

            if ($exists) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint): void {
                $blueprint->foreign('branch_id')
                    ->references('id')
                    ->on('branches')
                    ->nullOnDelete();
            });
        }
    }
};
