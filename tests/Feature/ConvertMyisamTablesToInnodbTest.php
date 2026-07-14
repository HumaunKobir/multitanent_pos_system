<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

test('converts myisam tables to innodb and restores missing primary keys', function () {
    if (Schema::getConnection()->getDriverName() !== 'mysql') {
        $this->markTestSkipped('MySQL engine conversion only applies to MySQL.');
    }

    $table = 'myisam_innodb_repair_test';

    DB::statement("DROP TABLE IF EXISTS `{$table}`");
    DB::statement("
        CREATE TABLE `{$table}` (
            `id` BIGINT UNSIGNED NOT NULL,
            `name` VARCHAR(50) NOT NULL
        ) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    DB::table($table)->insert(['id' => 1, 'name' => 'alpha']);

    $migration = require database_path('migrations/2026_07_13_170630_convert_myisam_tables_to_innodb.php');
    $migration->up();

    $status = collect(DB::select("SHOW TABLE STATUS LIKE '{$table}'"))->first();
    expect($status?->Engine)->toBe('InnoDB');

    $column = collect(DB::select("SHOW COLUMNS FROM `{$table}` LIKE 'id'"))->first();
    expect($column?->Key)->toBe('PRI');
    expect(strtolower((string) $column?->Extra))->toContain('auto_increment');

    DB::table($table)->insert(['name' => 'beta']);
    expect(DB::table($table)->where('name', 'beta')->value('id'))->toBeGreaterThan(1);

    DB::statement("DROP TABLE IF EXISTS `{$table}`");
});
