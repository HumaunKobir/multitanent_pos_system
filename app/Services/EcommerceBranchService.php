<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\User;

class EcommerceBranchService
{
    public const string BRANCH_NAME = Branch::ECOMMERCE_BRANCH_NAME;

    private static ?int $resolvedId = null;

    public function resolveId(): int
    {
        return self::resolveIdStatic();
    }

    public static function resolveIdStatic(): int
    {
        if (self::$resolvedId !== null) {
            return self::$resolvedId;
        }

        $branchId = Branch::query()
            ->where('name', self::BRANCH_NAME)
            ->value('id');

        if ($branchId === null) {
            $branchId = User::query()
                ->where('email', User::ECOMMERCE_BRANCH_ADMIN_EMAIL)
                ->value('branch_id');
        }

        self::$resolvedId = $branchId ?? Branch::MAIN_BRANCH_ID;

        return self::$resolvedId;
    }

    public function isEcommerceBranch(?int $branchId): bool
    {
        return self::isEcommerceBranchStatic($branchId);
    }

    public static function isEcommerceBranchStatic(?int $branchId): bool
    {
        return $branchId !== null && $branchId === self::resolveIdStatic();
    }

    public static function resetResolvedId(): void
    {
        self::$resolvedId = null;
    }

    /**
     * @return list<string>
     */
    public static function permissionNames(): array
    {
        return collect(config('permissions.modules', []))
            ->filter(fn (array $module): bool => ($module['group'] ?? '') === 'Ecommerce')
            ->flatMap(fn (array $module): array => array_keys($module['permissions']))
            ->values()
            ->all();
    }
}
