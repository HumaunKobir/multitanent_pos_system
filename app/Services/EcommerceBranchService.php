<?php

namespace App\Services;

use App\Models\Branch;

class EcommerceBranchService
{
    public const string BRANCH_NAME = 'Ecommerce';

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

        self::$resolvedId = Branch::query()
            ->where('name', self::BRANCH_NAME)
            ->value('id') ?? Branch::MAIN_BRANCH_ID;

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
}
