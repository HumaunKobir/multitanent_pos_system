<?php

namespace App\Support;

use Spatie\Permission\Models\Permission;

class PermissionCatalog
{
    /**
     * @return list<string>
     */
    public static function names(): array
    {
        return collect(config('permissions.modules', []))
            ->flatMap(fn (array $module) => array_keys($module['permissions']))
            ->values()
            ->all();
    }

    public static function isConfigured(string $name): bool
    {
        return in_array($name, self::names(), true);
    }

    /**
     * @param  list<string>  $names
     */
    public static function ensureExist(array $names): void
    {
        foreach ($names as $name) {
            Permission::firstOrCreate(
                ['name' => $name, 'guard_name' => 'web'],
            );
        }
    }
}
