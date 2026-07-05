<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

#[Signature('db:fix-auto-increments
                {--table= : Fix a specific table only}
                {--dry-run : Show changes without applying them}')]
#[Description('Restore AUTO_INCREMENT and primary keys on id columns after database import')]
class FixDatabaseAutoIncrements extends Command
{
    public function handle(): int
    {
        $driver = DB::connection()->getDriverName();

        if (! in_array($driver, ['mysql', 'mariadb'], true)) {
            $this->error("This command only supports MySQL/MariaDB (current: {$driver}).");

            return self::FAILURE;
        }

        $database = DB::connection()->getDatabaseName();
        $tables = $this->resolveTables($database);

        if ($tables === null) {
            return self::FAILURE;
        }

        if ($tables === []) {
            $this->info('No tables found.');

            return self::SUCCESS;
        }

        $dryRun = (bool) $this->option('dry-run');
        $fixed = 0;
        $skipped = 0;
        $failed = 0;

        $this->info($dryRun
            ? 'Scanning tables (dry run)...'
            : 'Fixing AUTO_INCREMENT on id columns...');

        foreach ($tables as $table) {
            $result = $this->fixTable($table, $dryRun);

            match ($result) {
                'fixed' => $fixed++,
                'skipped' => $skipped++,
                'failed' => $failed++,
            };
        }

        $this->newLine();
        $this->info("Done. {$fixed} fixed, {$skipped} skipped, {$failed} failed.");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * @return list<string>|null
     */
    protected function resolveTables(string $database): ?array
    {
        $tables = collect(DB::select('SHOW TABLES'))
            ->map(fn (object $row) => array_values((array) $row)[0])
            ->sort()
            ->values()
            ->all();

        $table = $this->option('table');

        if ($table === null) {
            return $tables;
        }

        if (! in_array($table, $tables, true)) {
            $this->error("Table [{$table}] not found in database [{$database}].");

            return null;
        }

        return [$table];
    }

    protected function fixTable(string $table, bool $dryRun): string
    {
        $column = $this->getIdColumn($table);

        if ($column === null) {
            return 'skipped';
        }

        if (str_contains($column->Extra ?? '', 'auto_increment')) {
            return 'skipped';
        }

        if (! $this->isIntegerIdColumn($column->Type)) {
            return 'skipped';
        }

        $type = $column->Type;
        $isPrimaryKey = ($column->Key ?? '') === 'PRI';
        $nextId = $this->getNextAutoIncrementValue($table);

        if ($dryRun) {
            $actions = [];

            if (! $isPrimaryKey) {
                $actions[] = 'ADD PRIMARY KEY (id)';
            }

            $actions[] = "MODIFY id {$type} NOT NULL AUTO_INCREMENT";
            $actions[] = "AUTO_INCREMENT = {$nextId}";

            $this->line("  [DRY RUN] {$table}: ".implode('; ', $actions));

            return 'fixed';
        }

        try {
            $alter = $isPrimaryKey
                ? "MODIFY `id` {$type} NOT NULL AUTO_INCREMENT, AUTO_INCREMENT = {$nextId}"
                : "ADD PRIMARY KEY (`id`), MODIFY `id` {$type} NOT NULL AUTO_INCREMENT, AUTO_INCREMENT = {$nextId}";

            DB::statement("ALTER TABLE `{$table}` {$alter}");

            $this->line("  <fg=green>FIXED</> {$table} (next id: {$nextId})");

            return 'fixed';
        } catch (Throwable $exception) {
            $this->line("  <fg=red>FAILED</> {$table}: {$exception->getMessage()}");

            return 'failed';
        }
    }

    protected function getIdColumn(string $table): ?object
    {
        $columns = DB::select("SHOW COLUMNS FROM `{$table}` WHERE Field = 'id'");

        return $columns[0] ?? null;
    }

    protected function isIntegerIdColumn(string $type): bool
    {
        return (bool) preg_match('/^(?:bigint|int|mediumint|smallint|tinyint)\b/i', $type);
    }

    protected function getNextAutoIncrementValue(string $table): int
    {
        $result = DB::selectOne("SELECT COALESCE(MAX(`id`), 0) + 1 AS next_id FROM `{$table}`");

        return (int) ($result->next_id ?? 1);
    }
}
