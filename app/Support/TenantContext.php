<?php

namespace App\Support;

use App\Models\Branch;
use RuntimeException;

class TenantContext
{
    private static ?Branch $branch = null;

    public static function set(?Branch $branch): void
    {
        self::$branch = $branch;
    }

    public static function clear(): void
    {
        self::$branch = null;
    }

    public static function branch(): ?Branch
    {
        return self::$branch;
    }

    public static function branchId(): ?int
    {
        return self::$branch?->id;
    }

    public static function requireBranch(): Branch
    {
        if (self::$branch === null) {
            throw new RuntimeException('No tenant branch is initialized.');
        }

        return self::$branch;
    }

    public static function initialized(): bool
    {
        return self::$branch !== null;
    }
}
