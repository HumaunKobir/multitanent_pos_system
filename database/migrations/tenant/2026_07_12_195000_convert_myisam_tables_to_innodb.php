<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Repair environments where tables were converted to MyISAM, lost PRIMARY KEYs,
     * or lost AUTO_INCREMENT.
     *
     * InnoDB foreign keys cannot reference MyISAM tables (MySQL error 1824), and they
     * also require an indexed referenced column. Safe/no-op on healthy InnoDB DBs.
     */
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'mysql') {
            return;
        }

        $this->convertMyisamTablesToInnodb();
        $this->restoreMissingPrimaryKeysAndAutoIncrement();
        $this->restoreKnownCompositePrimaryKeys();
    }

    public function down(): void
    {
        // Irreversible repair — do not convert live tables back to MyISAM.
    }

    private function convertMyisamTablesToInnodb(): void
    {
        $tables = DB::select(
            'SELECT TABLE_NAME
             FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_TYPE = ?
               AND ENGINE = ?',
            ['BASE TABLE', 'MyISAM'],
        );

        foreach ($tables as $table) {
            DB::statement('ALTER TABLE `'.$table->TABLE_NAME.'` ENGINE=InnoDB');
        }
    }

    private function restoreMissingPrimaryKeysAndAutoIncrement(): void
    {
        $tables = DB::select(
            'SELECT TABLE_NAME
             FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_TYPE = ?',
            ['BASE TABLE'],
        );

        foreach ($tables as $table) {
            $name = $table->TABLE_NAME;
            $primaryColumns = DB::select("SHOW COLUMNS FROM `{$name}` WHERE `Key` = 'PRI'");

            if (count($primaryColumns) > 1) {
                continue;
            }

            if (count($primaryColumns) === 1) {
                $this->ensureAutoIncrement($name, $primaryColumns[0]);

                continue;
            }

            $idColumn = collect(DB::select("SHOW COLUMNS FROM `{$name}` LIKE 'id'"))->first();

            if ($idColumn === null) {
                continue;
            }

            $this->ensurePrimaryKeyOnId($name, $idColumn);
        }
    }

    /**
     * @param  object{Field: string, Type: string, Null: string, Extra: string}  $column
     */
    private function ensureAutoIncrement(string $table, object $column): void
    {
        $type = strtolower((string) $column->Type);
        $extra = strtolower((string) $column->Extra);

        if (! str_contains($type, 'int') || str_contains($extra, 'auto_increment')) {
            return;
        }

        $nullSql = strtoupper((string) $column->Null) === 'YES' ? 'NULL' : 'NOT NULL';

        DB::statement("ALTER TABLE `{$table}` MODIFY `{$column->Field}` {$column->Type} {$nullSql} AUTO_INCREMENT");
    }

    /**
     * @param  object{Field: string, Type: string, Null: string, Extra: string}  $column
     */
    private function ensurePrimaryKeyOnId(string $table, object $column): void
    {
        $type = strtolower((string) $column->Type);
        $nullSql = strtoupper((string) $column->Null) === 'YES' ? 'NULL' : 'NOT NULL';

        if (str_contains($type, 'int')) {
            $duplicates = (int) (DB::selectOne(
                "SELECT COUNT(*) AS aggregate_count
                 FROM (
                     SELECT `id`
                     FROM `{$table}`
                     GROUP BY `id`
                     HAVING COUNT(*) > 1
                 ) duplicate_ids",
            )->aggregate_count ?? 0);

            if ($duplicates > 0) {
                throw new RuntimeException(
                    "Cannot restore PRIMARY KEY on {$table}.id — {$duplicates} duplicate id value(s) found.",
                );
            }

            DB::statement(
                "ALTER TABLE `{$table}` MODIFY `id` {$column->Type} {$nullSql} AUTO_INCREMENT PRIMARY KEY",
            );

            return;
        }

        if (str_contains($type, 'char') || str_contains($type, 'binary')) {
            DB::statement("ALTER TABLE `{$table}` ADD PRIMARY KEY (`id`)");
        }
    }

    /**
     * Pivot tables without an `id` column still need their composite PRIMARY KEY restored.
     */
    private function restoreKnownCompositePrimaryKeys(): void
    {
        /** @var array<string, list<string>> $composites */
        $composites = [
            'role_has_permissions' => ['permission_id', 'role_id'],
            'model_has_roles' => ['role_id', 'model_id', 'model_type'],
            'model_has_permissions' => ['permission_id', 'model_id', 'model_type'],
        ];

        foreach ($composites as $table => $columns) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            $hasPrimary = collect(DB::select(
                'SELECT CONSTRAINT_NAME
                 FROM information_schema.TABLE_CONSTRAINTS
                 WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME = ?
                   AND CONSTRAINT_TYPE = ?',
                [$table, 'PRIMARY KEY'],
            ))->isNotEmpty();

            if ($hasPrimary) {
                continue;
            }

            $columnList = collect($columns)
                ->map(fn (string $column): string => "`{$column}`")
                ->implode(', ');

            DB::statement("ALTER TABLE `{$table}` ADD PRIMARY KEY ({$columnList})");
        }
    }
};
