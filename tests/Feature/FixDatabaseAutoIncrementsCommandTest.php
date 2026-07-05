<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

const FIX_AUTO_INCREMENT_TEST_TABLE = 'fix_auto_increment_test_table';

beforeEach(function () {
    DB::statement('DROP TABLE IF EXISTS `'.FIX_AUTO_INCREMENT_TEST_TABLE.'`');
    DB::statement(
        'CREATE TABLE `'.FIX_AUTO_INCREMENT_TEST_TABLE.'` (
            `id` bigint unsigned NOT NULL,
            `name` varchar(191) NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );
    DB::table(FIX_AUTO_INCREMENT_TEST_TABLE)->insert([
        ['id' => 1, 'name' => 'first'],
        ['id' => 2, 'name' => 'second'],
    ]);
});

afterEach(function () {
    DB::statement('DROP TABLE IF EXISTS `'.FIX_AUTO_INCREMENT_TEST_TABLE.'`');
});

it('restores auto increment and primary key on id column', function () {
    Artisan::call('db:fix-auto-increments', [
        '--table' => FIX_AUTO_INCREMENT_TEST_TABLE,
    ]);

    expect(Artisan::output())->toContain('FIXED');

    $column = DB::selectOne(
        'SHOW COLUMNS FROM `'.FIX_AUTO_INCREMENT_TEST_TABLE."` WHERE Field = 'id'"
    );

    expect($column->Key)->toBe('PRI')
        ->and($column->Extra)->toContain('auto_increment');

    DB::table(FIX_AUTO_INCREMENT_TEST_TABLE)->insert(['name' => 'third']);

    expect((int) DB::table(FIX_AUTO_INCREMENT_TEST_TABLE)->orderByDesc('id')->value('id'))->toBe(3);
});

it('skips tables that already have auto increment', function () {
    Artisan::call('db:fix-auto-increments', [
        '--table' => FIX_AUTO_INCREMENT_TEST_TABLE,
    ]);

    Artisan::call('db:fix-auto-increments', [
        '--table' => FIX_AUTO_INCREMENT_TEST_TABLE,
    ]);

    expect(Artisan::output())->toContain('0 fixed');
});

it('reports planned changes in dry run mode', function () {
    Artisan::call('db:fix-auto-increments', [
        '--table' => FIX_AUTO_INCREMENT_TEST_TABLE,
        '--dry-run' => true,
    ]);

    $output = Artisan::output();

    expect($output)->toContain('[DRY RUN]')
        ->and($output)->toContain('ADD PRIMARY KEY (id)')
        ->and($output)->toContain('AUTO_INCREMENT = 3');

    $column = DB::selectOne(
        'SHOW COLUMNS FROM `'.FIX_AUTO_INCREMENT_TEST_TABLE."` WHERE Field = 'id'"
    );

    expect($column->Extra)->not->toContain('auto_increment');
});

it('fails when the table does not exist', function () {
    $exitCode = Artisan::call('db:fix-auto-increments', [
        '--table' => 'missing_table_xyz',
    ]);

    expect($exitCode)->toBe(1)
        ->and(Artisan::output())->toContain('not found');
});
