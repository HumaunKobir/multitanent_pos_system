<?php

namespace App\Services;

use App\Models\Barcode;
use App\Models\Product;
use App\Models\ProductVariation;

class BarcodeService
{
    public const MAX_LENGTH = 12;

    public function generateUniqueSharedCode(
        ?string $productGroupId = null,
        ?int $excludeProductId = null,
        ?int $excludeVariationId = null,
    ): string {
        do {
            $code = (string) random_int(1_000_000_000, 9_99_999_999_999);
        } while (! $this->codeIsAvailableGlobally($code, $productGroupId, $excludeProductId, $excludeVariationId));

        return $code;
    }

    /**
     * @deprecated Use generateUniqueSharedCode() — kept for single-branch callers.
     */
    public function generateUniqueForBranch(int $branchId, ?int $excludeProductId = null, ?int $excludeVariationId = null): string
    {
        return $this->generateUniqueSharedCode(null, $excludeProductId, $excludeVariationId);
    }

    /**
     * @param  iterable<int>  $branchIds
     */
    public function generateUniqueForBranches(
        iterable $branchIds,
        ?int $excludeProductId = null,
        ?int $excludeVariationId = null,
        ?string $productGroupId = null,
    ): string {
        return $this->generateUniqueSharedCode($productGroupId, $excludeProductId, $excludeVariationId);
    }

    /**
     * Resolve one product barcode shared by every branch copy in a product group.
     *
     * @param  iterable<int>  $branchIds
     */
    public function resolveSharedProductCode(
        ?string $manualCode,
        iterable $branchIds,
        ?string $productGroupId = null,
        ?int $excludeProductId = null,
    ): ?string {
        $manualCode = filled($manualCode) ? trim((string) $manualCode) : null;

        if ($manualCode !== null) {
            if ($this->codeIsAvailableGlobally($manualCode, $productGroupId, $excludeProductId)) {
                return $manualCode;
            }

            return $this->generateUniqueSharedCode($productGroupId, $excludeProductId);
        }

        return $this->generateUniqueSharedCode($productGroupId, $excludeProductId);
    }

    public function resolveVariationBarcode(
        int $branchId,
        ?string $submittedSku,
        ?int $excludeVariationId = null,
        ?int $excludeProductId = null,
        ?string $productGroupId = null,
    ): string {
        $submittedSku = trim((string) $submittedSku);

        if (
            $submittedSku !== ''
            && strlen($submittedSku) <= self::MAX_LENGTH
            && $this->codeIsAvailableGlobally($submittedSku, $productGroupId, $excludeProductId, $excludeVariationId)
        ) {
            return $submittedSku;
        }

        return $this->generateUniqueSharedCode($productGroupId, $excludeProductId, $excludeVariationId);
    }

    /**
     * Whether a barcode can be used — free everywhere except copies of the same product group.
     */
    public function codeIsAvailableGlobally(
        string $code,
        ?string $productGroupId = null,
        ?int $excludeProductId = null,
        ?int $excludeVariationId = null,
    ): bool {
        $products = Product::query()
            ->where('code', $code)
            ->when($excludeProductId, fn ($query) => $query->where('id', '!=', $excludeProductId))
            ->get(['id', 'product_group_id']);

        foreach ($products as $product) {
            if (! $this->isSameProductGroup($productGroupId, $product->product_group_id)) {
                return false;
            }
        }

        $variations = ProductVariation::query()
            ->where('sku', $code)
            ->when($excludeVariationId, fn ($query) => $query->where('id', '!=', $excludeVariationId))
            ->with('product:id,product_group_id')
            ->get();

        foreach ($variations as $variation) {
            if (! $this->isSameProductGroup($productGroupId, $variation->product?->product_group_id)) {
                return false;
            }
        }

        $barcodes = Barcode::query()
            ->where('code', $code)
            ->with(['product:id,product_group_id', 'variation.product:id,product_group_id'])
            ->get();

        foreach ($barcodes as $barcode) {
            if ($barcode->product_variation_id === $excludeVariationId) {
                continue;
            }

            $groupId = $barcode->product?->product_group_id
                ?? $barcode->variation?->product?->product_group_id;

            if (! $this->isSameProductGroup($productGroupId, $groupId)) {
                return false;
            }
        }

        return true;
    }

    public function codeExistsInBranch(
        int $branchId,
        string $code,
        ?int $excludeProductId = null,
        ?int $excludeVariationId = null,
    ): bool {
        if (Product::query()
            ->where('branch_id', $branchId)
            ->where('code', $code)
            ->when($excludeProductId, fn ($query) => $query->where('id', '!=', $excludeProductId))
            ->exists()) {
            return true;
        }

        if (ProductVariation::query()
            ->where('branch_id', $branchId)
            ->where('sku', $code)
            ->when($excludeVariationId, fn ($query) => $query->where('id', '!=', $excludeVariationId))
            ->exists()) {
            return true;
        }

        return Barcode::query()
            ->where('branch_id', $branchId)
            ->where('code', $code)
            ->when($excludeVariationId, function ($query) use ($excludeVariationId) {
                $query->where(function ($inner) use ($excludeVariationId) {
                    $inner->whereNull('product_variation_id')
                        ->orWhere('product_variation_id', '!=', $excludeVariationId);
                });
            })
            ->exists();
    }

    /**
     * @param  array<int, array<string, mixed>>  $combinations
     * @return array<int, array<string, mixed>>
     */
    public function normalizeCombinationsForBranches(
        iterable $branchIds,
        array $combinations,
        ?string $productGroupId = null,
    ): array {
        return array_map(function (array $combo) use ($productGroupId): array {
            $excludeVariationId = isset($combo['id']) ? (int) $combo['id'] : null;
            $submittedSku = trim((string) ($combo['sku'] ?? ''));

            if (
                $submittedSku !== ''
                && strlen($submittedSku) <= self::MAX_LENGTH
                && $this->codeIsAvailableGlobally($submittedSku, $productGroupId, excludeVariationId: $excludeVariationId)
            ) {
                $combo['sku'] = $submittedSku;

                return $combo;
            }

            $combo['sku'] = $this->generateUniqueSharedCode($productGroupId, excludeVariationId: $excludeVariationId);

            return $combo;
        }, $combinations);
    }

    /**
     * @param  array<int, array<string, mixed>>  $combinations
     * @return array<int, array<string, mixed>>
     */
    public function normalizeCombinationsForBranch(
        int $branchId,
        array $combinations,
        ?string $productGroupId = null,
        ?int $excludeProductId = null,
    ): array {
        return array_map(function (array $combo) use ($branchId, $productGroupId, $excludeProductId): array {
            $excludeVariationId = isset($combo['id']) ? (int) $combo['id'] : null;

            $combo['sku'] = $this->resolveVariationBarcode(
                $branchId,
                $combo['sku'] ?? null,
                $excludeVariationId,
                $excludeProductId,
                $productGroupId,
            );

            return $combo;
        }, $combinations);
    }

    private function isSameProductGroup(?string $requestedGroupId, ?string $existingGroupId): bool
    {
        if ($requestedGroupId === null || $existingGroupId === null) {
            return false;
        }

        return $requestedGroupId === $existingGroupId;
    }
}
