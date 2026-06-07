<?php

namespace App\Concerns;

use App\Support\StorageUrl;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

trait StoresPublicImages
{
    /**
     * @throws ValidationException
     */
    protected function storePublicImage(Request $request, string $field, string $directory): ?string
    {
        if (! $request->hasFile($field)) {
            return null;
        }

        $this->ensurePublicUploadDirectory($field, $directory);

        /** @var UploadedFile $file */
        $file = $request->file($field);
        $path = $file->store($directory, 'public');

        if (! is_string($path) || $path === '' || ! Storage::disk('public')->exists($path)) {
            throw ValidationException::withMessages([
                $field => 'The image could not be uploaded. Please try again.',
            ]);
        }

        return $path;
    }

    /**
     * @throws ValidationException
     */
    protected function ensurePublicUploadDirectory(string $field, string $directory): void
    {
        $disk = Storage::disk('public');

        if (! $disk->exists($directory)) {
            $disk->makeDirectory($directory);
        }

        if (! is_writable($disk->path($directory))) {
            throw ValidationException::withMessages([
                $field => 'The image upload folder is not writable. Please contact your administrator.',
            ]);
        }
    }

    protected function deletePublicImage(?string $path): void
    {
        if ($path && Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
        }
    }

    protected function isStoredPublicImage(?string $path): bool
    {
        return is_string($path)
            && $path !== ''
            && $path !== '0'
            && ! str_starts_with($path, 'http://')
            && ! str_starts_with($path, 'https://');
    }

    protected function publicImageUrl(?string $path): ?string
    {
        if (! $this->isStoredPublicImage($path) || ! Storage::disk('public')->exists($path)) {
            return null;
        }

        return StorageUrl::public($path);
    }
}
