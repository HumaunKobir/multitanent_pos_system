<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class SyncPermissions extends Command
{
    protected $signature = 'permissions:sync
                            {--cleanup : Delete permissions that are no longer defined in config}';

    protected $description = 'Sync permissions from config/permissions.php into the database';

    public function handle(): int
    {
        $modules = config('permissions.modules', []);

        // Collect all permission names defined in config
        $configNames = collect($modules)
            ->flatMap(fn (array $module) => array_keys($module['permissions']))
            ->values();

        // ── Create missing permissions ────────────────────────────────────────
        $created = 0;
        $existing = 0;
        $createdNames = [];

        foreach ($modules as $module) {
            foreach (array_keys($module['permissions']) as $name) {
                $permission = Permission::firstOrCreate(
                    ['name' => $name, 'guard_name' => 'web'],
                );

                if ($permission->wasRecentlyCreated) {
                    $this->line("  <fg=green>CREATED</> {$name}");
                    $created++;
                    $createdNames[] = $name;
                } else {
                    $existing++;
                }
            }
        }

        $this->inheritCoinSettingsPermissions($createdNames);
        $this->migrateRenamedPermissions();

        $this->newLine();
        $this->info("Sync done. {$created} created, {$existing} already existed.");

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        // ── Cleanup orphaned permissions ──────────────────────────────────────
        if ($this->option('cleanup')) {
            $this->newLine();
            $this->line('<fg=yellow>Running cleanup...</>');

            $orphaned = Permission::where('guard_name', 'web')
                ->whereNotIn('name', $configNames)
                ->get();

            if ($orphaned->isEmpty()) {
                $this->line('  Nothing to clean up.');

                return self::SUCCESS;
            }

            foreach ($orphaned as $permission) {
                $permission->delete();
                $this->line("  <fg=red>DELETED</> {$permission->name}");
            }

            $this->newLine();
            $this->info("Cleanup done. {$orphaned->count()} permission(s) deleted.");
        }

        return self::SUCCESS;
    }

    /**
     * @param  list<string>  $createdNames
     */
    protected function inheritCoinSettingsPermissions(array $createdNames): void
    {
        $coinPermissions = [
            'setting.coin-settings.view',
            'setting.coin-settings.create',
            'setting.coin-settings.update',
        ];

        if (array_intersect($createdNames, $coinPermissions) === []) {
            return;
        }

        $anchorPermissions = [
            'setting.pos-terms.view',
            'setting.special-discount.view',
        ];

        $roles = Role::query()
            ->whereHas('permissions', fn ($query) => $query->whereIn('name', $anchorPermissions))
            ->get();

        foreach ($roles as $role) {
            $role->givePermissionTo($coinPermissions);
        }

        if ($roles->isNotEmpty()) {
            $this->newLine();
            $this->line("  <fg=cyan>INHERITED</> coin settings permissions to {$roles->count()} role(s) with POS/special discount access.");
        }
    }

    protected function migrateRenamedPermissions(): void
    {
        $migrations = [
            'inventory.stock.view' => 'report.inventory-stock.view',
        ];

        foreach ($migrations as $from => $to) {
            $oldPermission = Permission::query()->where('name', $from)->where('guard_name', 'web')->first();

            if ($oldPermission === null) {
                continue;
            }

            $newPermission = Permission::firstOrCreate(['name' => $to, 'guard_name' => 'web']);

            $roles = Role::query()->whereHas('permissions', fn ($query) => $query->where('name', $from))->get();

            foreach ($roles as $role) {
                $role->givePermissionTo($newPermission);
                $role->revokePermissionTo($oldPermission);
            }

            $oldPermission->delete();

            if ($roles->isNotEmpty()) {
                $this->line("  <fg=cyan>MIGRATED</> {$from} → {$to} for {$roles->count()} role(s).");
            }
        }
    }
}
