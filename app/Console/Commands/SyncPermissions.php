<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Spatie\Permission\Models\Permission;
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

        foreach ($modules as $module) {
            foreach (array_keys($module['permissions']) as $name) {
                $permission = Permission::firstOrCreate(
                    ['name' => $name, 'guard_name' => 'web'],
                );

                if ($permission->wasRecentlyCreated) {
                    $this->line("  <fg=green>CREATED</> {$name}");
                    $created++;
                } else {
                    $existing++;
                }
            }
        }

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
}
