<?php

namespace App\Support;

final class StorageUrl
{
    public static function public(?string $path): ?string
    {
        if (empty($path)) {
            return null;
        }

        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://') || str_starts_with($path, '/')) {
            return $path;
        }

        // Root-relative URLs so images work regardless of APP_URL / domain.
        return '/storage/'.ltrim(str_replace('\\', '/', $path), '/');
    }
}
