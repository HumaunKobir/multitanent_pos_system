<?php

namespace App\Services;

use App\Enums\CommonStatus;
use App\Models\Barcode;
use App\Models\Branch;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Color;
use App\Models\OnlineOrderProduct;
use App\Models\Product;
use App\Models\ProductPhoto;
use App\Models\ProductVariation;
use App\Models\Size;
use App\Models\Tag;
use App\Models\Unit;
use App\Models\Warranty;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ProductBranchReplicationService
{
    public function __construct(
        private BranchCatalogReplicationService $catalogReplication,
        private ProductInitialStockService $initialStock,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     * @param  array<int, array<string, mixed>>  $combinations
     * @param  list<string>  $photoPaths
     * @return list<Product>
     */
    public function createForBranches(
        array $data,
        array $combinations,
        float $mainPurchasePrice,
        float $mainSalePrice,
        int $mainInitialStock = 0,
        array $photoPaths = [],
        ?string $productGroupId = null,
        ?Collection $branchIds = null,
    ): array {
        $productGroupId ??= (string) Str::uuid();
        $branchIds ??= Branch::query()->active()->orderBy('id')->pluck('id');
        $manualCode = filled($data['code'] ?? null) ? (string) $data['code'] : null;
        $baseSlug = filled($data['slug'] ?? null)
            ? (string) $data['slug']
            : Product::generateUniqueSlug((string) $data['name']);
        $autoCodeBase = $manualCode ?? Product::generateUniqueBarcodeNumber();
        $created = [];

        foreach ($branchIds as $branchId) {
            $branchId = (int) $branchId;
            $branchData = $this->mapBranchCatalogFields($data, $branchId);
            $branchData['branch_id'] = $branchId;
            $branchData['product_group_id'] = $productGroupId;
            $branchData['slug'] = $this->resolveBranchSlug($baseSlug, $branchId);
            $branchData['code'] = $this->resolveBranchCode($manualCode ?? $autoCodeBase, $branchId);

            if (! Branch::isMainBranch($branchId)) {
                unset($branchData['selected_branch_id']);
            }

            $product = Product::create($branchData);

            $this->ensureTagRecordsForBranch($branchData['tags'] ?? [], $branchId);

            foreach ($photoPaths as $path) {
                ProductPhoto::create([
                    'product_id' => $product->id,
                    'image' => $path,
                ]);
            }

            if ($combinations === [] && $product->code) {
                Barcode::create([
                    'branch_id' => $branchId,
                    'product_id' => $product->id,
                    'product_variation_id' => null,
                    'code' => $product->code,
                    'name' => $product->name,
                ]);
            }

            if ($combinations === []) {
                $initialStockQty = Branch::isMainBranch($branchId) ? $mainInitialStock : 0;
                $this->initialStock->syncNonVariant($product, $initialStockQty, $mainPurchasePrice);
            }

            foreach ($combinations as $combo) {
                $salePrice = (isset($combo['sale_price']) && (string) $combo['sale_price'] !== '')
                    ? $combo['sale_price']
                    : $mainSalePrice;

                $purchasePrice = (isset($combo['purchase_price']) && (string) $combo['purchase_price'] !== '')
                    ? $combo['purchase_price']
                    : $mainPurchasePrice;

                $stock = Branch::isMainBranch($branchId)
                    ? $this->initialStock->resolveComboStock($combo, $mainInitialStock)
                    : 0;

                $variation = ProductVariation::create([
                    'product_id' => $product->id,
                    'branch_id' => $branchId,
                    'sku' => $combo['sku'],
                    'price' => $salePrice,
                    'purchase_price' => $purchasePrice,
                    'stock' => 0,
                    'variation_data' => $combo['variation_data'] ?? ['label' => $combo['variant']],
                ]);

                $this->initialStock->applyVariationStockOnCreate($variation, $stock, (float) $purchasePrice);

                Barcode::create([
                    'branch_id' => $branchId,
                    'product_id' => $product->id,
                    'product_variation_id' => $variation->id,
                    'code' => $combo['sku'],
                    'name' => $product->name.' - '.$combo['variant'],
                ]);
            }

            $created[] = $product;
        }

        return $created;
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<int, array<string, mixed>>  $combinations
     * @param  list<UploadedFile>  $photos
     * @return list<Product>
     */
    public function createSingle(
        array $data,
        array $combinations,
        float $mainPurchasePrice,
        float $mainSalePrice,
        int $mainInitialStock = 0,
        array $photoPaths = [],
    ): array {
        $user = Auth::user();

        if ($user?->usesBranchPanel()) {
            return $this->createBranchSubmissionWithPendingMain(
                $data,
                $combinations,
                $mainPurchasePrice,
                $mainSalePrice,
                $mainInitialStock,
                $photoPaths,
                (int) $user->branch_id,
            );
        }

        $branchId = $this->resolveStoreBranchId(
            filled($data['branch_id'] ?? null) ? (int) $data['branch_id'] : null,
        );

        if ($branchId === null) {
            unset($data['branch_id']);

            return $this->createForBranches(
                $data,
                $combinations,
                $mainPurchasePrice,
                $mainSalePrice,
                $mainInitialStock,
                $photoPaths,
            );
        }

        if (! Branch::isMainBranch($branchId)) {
            unset($data['branch_id']);

            return $this->createForBranches(
                $data,
                $combinations,
                $mainPurchasePrice,
                $mainSalePrice,
                $mainInitialStock,
                $photoPaths,
                branchIds: collect([
                    Branch::resolveMainBranchId(),
                    $branchId,
                ])->sort()->values(),
            );
        }

        $data['branch_id'] = $branchId;

        return [
            $this->persistProductAtBranch(
                $data,
                $combinations,
                $mainPurchasePrice,
                $mainSalePrice,
                $mainInitialStock,
                $photoPaths,
                $branchId,
            ),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<int, array<string, mixed>>  $combinations
     * @param  list<string>  $photoPaths
     * @return list<Product>
     */
    private function createBranchSubmissionWithPendingMain(
        array $data,
        array $combinations,
        float $mainPurchasePrice,
        float $mainSalePrice,
        int $mainInitialStock,
        array $photoPaths,
        int $branchId,
    ): array {
        $productGroupId = (string) Str::uuid();
        $mainBranchId = Branch::resolveMainBranchId();
        $manualCode = filled($data['code'] ?? null) ? (string) $data['code'] : null;
        $baseSlug = filled($data['slug'] ?? null)
            ? (string) $data['slug']
            : Product::generateUniqueSlug((string) $data['name']);
        $autoCodeBase = $manualCode ?? Product::generateUniqueBarcodeNumber();

        $branchData = $this->mapBranchCatalogFields($data, $branchId);
        $branchData['branch_id'] = $branchId;
        $branchData['product_group_id'] = $productGroupId;
        $branchData['slug'] = $this->resolveBranchSlug($baseSlug, $branchId);
        $branchData['code'] = $this->resolveBranchCode($manualCode ?? $autoCodeBase, $branchId);

        $branchProduct = $this->persistProductAtBranch(
            $branchData,
            $combinations,
            $mainPurchasePrice,
            $mainSalePrice,
            $mainInitialStock,
            $photoPaths,
            $branchId,
        );

        $mainData = $this->mapBranchCatalogFields($data, $mainBranchId);
        $mainData['branch_id'] = $mainBranchId;
        $mainData['product_group_id'] = $productGroupId;
        $mainData['source_branch_id'] = $branchId;
        $mainData['received_at'] = null;
        $mainData['slug'] = $this->resolveBranchSlug($baseSlug, $mainBranchId);
        $mainData['code'] = $this->resolveBranchCode($manualCode ?? $autoCodeBase, $mainBranchId);

        $mainCombinations = array_map(function (array $combo): array {
            unset($combo['stock']);

            return $combo;
        }, $combinations);

        $this->persistProductAtBranch(
            $mainData,
            $mainCombinations,
            $mainPurchasePrice,
            $mainSalePrice,
            0,
            $photoPaths,
            $mainBranchId,
        );

        $this->ensureTagRecordsForBranch($branchData['tags'] ?? [], $branchId);
        $this->ensureTagRecordsForBranch($mainData['tags'] ?? [], $mainBranchId);

        return [$branchProduct];
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<int, array<string, mixed>>  $combinations
     * @param  list<string>  $photoPaths
     */
    private function persistProductAtBranch(
        array $data,
        array $combinations,
        float $mainPurchasePrice,
        float $mainSalePrice,
        int $mainInitialStock,
        array $photoPaths,
        int $branchId,
    ): Product {
        $product = Product::create($data);

        foreach ($photoPaths as $path) {
            ProductPhoto::create([
                'product_id' => $product->id,
                'image' => $path,
            ]);
        }

        if ($combinations === [] && $product->code) {
            Barcode::create([
                'branch_id' => $branchId,
                'product_id' => $product->id,
                'product_variation_id' => null,
                'code' => $product->code,
                'name' => $product->name,
            ]);
        }

        if ($combinations === []) {
            $this->initialStock->syncNonVariant($product, $mainInitialStock, $mainPurchasePrice);
        }

        foreach ($combinations as $combo) {
            $salePrice = (isset($combo['sale_price']) && (string) $combo['sale_price'] !== '')
                ? $combo['sale_price']
                : $mainSalePrice;

            $purchasePrice = (isset($combo['purchase_price']) && (string) $combo['purchase_price'] !== '')
                ? $combo['purchase_price']
                : $mainPurchasePrice;

            $stock = $this->initialStock->resolveComboStock($combo, $mainInitialStock);

            $variation = ProductVariation::create([
                'product_id' => $product->id,
                'branch_id' => $branchId,
                'sku' => $combo['sku'],
                'price' => $salePrice,
                'purchase_price' => $purchasePrice,
                'stock' => 0,
                'variation_data' => $combo['variation_data'] ?? ['label' => $combo['variant']],
            ]);

            $this->initialStock->applyVariationStockOnCreate($variation, $stock, (float) $purchasePrice);

            Barcode::create([
                'branch_id' => $branchId,
                'product_id' => $product->id,
                'product_variation_id' => $variation->id,
                'code' => $combo['sku'],
                'name' => $product->name.' - '.$combo['variant'],
            ]);
        }

        return $product;
    }

    private function resolveStoreBranchId(?int $requestedBranchId): ?int
    {
        $user = Auth::user();

        if ($user?->usesBranchPanel()) {
            return (int) $user->branch_id;
        }

        return $requestedBranchId;
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<int, array<string, mixed>>  $combinations
     */
    public function resolveStoredSelection(Product $product): ?int
    {
        if ($product->selected_branch_id !== null) {
            return (int) $product->selected_branch_id;
        }

        if ($product->product_group_id !== null) {
            $mainProduct = $this->mainSiblingInGroup($product);

            if ($mainProduct?->selected_branch_id !== null) {
                return (int) $mainProduct->selected_branch_id;
            }
        }

        return null;
    }

    public function resolveFormBranchSelection(Product $product): string
    {
        $selection = $this->resolveStoredSelection($product);

        if ($selection !== null) {
            return (string) $selection;
        }

        if ($product->product_group_id !== null) {
            return '';
        }

        if (Branch::isMainBranch((int) $product->branch_id)) {
            return (string) $product->branch_id;
        }

        return '';
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<int, array<string, mixed>>  $combinations
     */
    public function applyBranchSelectionChange(
        Product $sourceProduct,
        int $targetBranchId,
        array $data,
        array $combinations,
        float $mainPurchasePrice,
        float $mainSalePrice,
        int $mainInitialStock = 0,
    ): void {
        $mainBranchId = Branch::resolveMainBranchId();
        $mainProduct = $this->mainSiblingInGroup($sourceProduct);

        if ($mainProduct === null && Branch::isMainBranch((int) $sourceProduct->branch_id)) {
            $mainProduct = $sourceProduct;
        }

        if ($mainProduct === null) {
            return;
        }

        $siblings = $this->siblings($mainProduct);
        $keepBranchIds = collect([$mainBranchId, $targetBranchId])->unique()->values();

        $toRemove = $siblings->filter(
            fn (Product $sibling): bool => ! $keepBranchIds->contains((int) $sibling->branch_id),
        );

        foreach ($toRemove as $sibling) {
            if ($this->productHasBranchActivity($sibling)) {
                throw ValidationException::withMessages([
                    'branch_id' => 'Branch selection cannot be changed because another branch already has purchase, sale, or online order history for this product.',
                ]);
            }
        }

        $targetCopy = $siblings->first(
            fn (Product $sibling): bool => (int) $sibling->branch_id === $targetBranchId,
        );

        if ($targetCopy === null) {
            $this->copyToBranch(
                $mainProduct->fresh(['photos', 'variations']),
                $targetBranchId,
                $data,
                $combinations,
                $mainPurchasePrice,
                $mainSalePrice,
                $mainInitialStock,
            );
        }

        foreach ($toRemove as $sibling) {
            $this->deleteCatalogProduct($sibling);
        }

        $mainProduct->update(['selected_branch_id' => $targetBranchId]);

        $this->siblings($mainProduct->fresh())
            ->filter(fn (Product $sibling): bool => ! Branch::isMainBranch((int) $sibling->branch_id))
            ->each(fn (Product $sibling) => $sibling->update(['selected_branch_id' => null]));
    }

    public function copyToBranch(
        Product $source,
        int $targetBranchId,
        array $data,
        array $combinations,
        float $mainPurchasePrice,
        float $mainSalePrice,
        int $mainInitialStock = 0,
    ): Product {
        $baseSlug = $this->resolveBaseSlug($source->slug, (int) $source->branch_id);
        $baseCode = $this->resolveBaseCode($source->code, (int) $source->branch_id);
        $productGroupId = $source->product_group_id ?? (string) Str::uuid();

        if ($source->product_group_id === null) {
            $source->update(['product_group_id' => $productGroupId]);
        }

        $copyData = $this->mapBranchCatalogFields($data, $targetBranchId);
        $copyData['branch_id'] = $targetBranchId;
        $copyData['product_group_id'] = $productGroupId;
        unset($copyData['selected_branch_id']);
        $copyData['slug'] = $this->resolveBranchSlug($baseSlug, $targetBranchId);
        $copyData['code'] = $this->resolveBranchCode($baseCode, $targetBranchId);
        $copyData['image'] = filled($data['image'] ?? null) ? $data['image'] : $source->image;
        $copyData['chest_size_image'] = filled($data['chest_size_image'] ?? null) ? $data['chest_size_image'] : $source->chest_size_image;

        $copyCombinations = $combinations;
        if ($copyCombinations === [] && $source->variations()->exists()) {
            $copyCombinations = $source->variations->map(fn (ProductVariation $variation): array => [
                'variant' => $variation->variation_data['label'] ?? '',
                'variation_data' => $variation->variation_data,
                'sale_price' => $variation->price,
                'purchase_price' => $variation->purchase_price,
                'sku' => $variation->sku,
                'stock' => $variation->stock,
            ])->all();
        }

        $copy = $this->persistProductAtBranch(
            $copyData,
            $copyCombinations,
            $mainPurchasePrice,
            $mainSalePrice,
            $mainInitialStock,
            $source->photos()->pluck('image')->all(),
            $targetBranchId,
        );

        return $copy;
    }

    public function expandGroupToAllBranches(
        Product $sourceProduct,
        array $data,
        array $combinations,
        float $mainPurchasePrice,
        float $mainSalePrice,
        int $mainInitialStock = 0,
    ): void {
        $mainBranchId = Branch::resolveMainBranchId();
        $mainProduct = $this->mainSiblingInGroup($sourceProduct);

        if ($mainProduct === null && Branch::isMainBranch((int) $sourceProduct->branch_id)) {
            $mainProduct = $sourceProduct;
        }

        if ($mainProduct === null) {
            return;
        }

        $productGroupId = $mainProduct->product_group_id ?? (string) Str::uuid();

        if ($mainProduct->product_group_id === null) {
            $mainProduct->update(['product_group_id' => $productGroupId]);
        }

        $existingBranchIds = $this->siblings($mainProduct->fresh())
            ->pluck('branch_id')
            ->map(fn ($branchId): int => (int) $branchId);

        $missingBranchIds = Branch::query()
            ->active()
            ->orderBy('id')
            ->pluck('id')
            ->filter(fn (int $branchId): bool => ! $existingBranchIds->contains($branchId));

        $manualCode = filled($data['code'] ?? null) ? (string) $data['code'] : $mainProduct->code;
        $replicationData = $data;
        unset($replicationData['branch_id'], $replicationData['selected_branch_id']);
        $replicationData['slug'] = $this->resolveBaseSlug($mainProduct->slug, (int) $mainProduct->branch_id);
        $replicationData['code'] = $this->resolveBaseCode($manualCode, (int) $mainProduct->branch_id);

        $photoPaths = $mainProduct->photos()->pluck('image')->all();

        if ($missingBranchIds->isNotEmpty()) {
            $this->createForBranches(
                $replicationData,
                $combinations,
                $mainPurchasePrice,
                $mainSalePrice,
                $mainInitialStock,
                $photoPaths,
                $productGroupId,
                $missingBranchIds,
            );
        }

        $mainProduct->update(['selected_branch_id' => null]);

        Product::query()
            ->where('product_group_id', $productGroupId)
            ->where('branch_id', '!=', $mainBranchId)
            ->update(['selected_branch_id' => null]);
    }

    public function expandToAllBranches(
        Product $sourceProduct,
        array $data,
        array $combinations,
        float $mainPurchasePrice,
        float $mainSalePrice,
        int $mainInitialStock,
    ): void {
        $productGroupId = (string) Str::uuid();

        $sourceProduct->update(['product_group_id' => $productGroupId]);

        $manualCode = filled($data['code'] ?? null) ? (string) $data['code'] : $sourceProduct->code;
        $sourceBranchId = (int) $sourceProduct->branch_id;

        if ($manualCode !== null && ! Branch::isMainBranch($sourceBranchId) && $sourceProduct->code === $manualCode) {
            $branchCode = $this->resolveBranchCode($manualCode, $sourceBranchId);
            $sourceProduct->update(['code' => $branchCode]);

            Barcode::query()
                ->where('product_id', $sourceProduct->id)
                ->whereNull('product_variation_id')
                ->update(['code' => $branchCode]);
        }

        $missingBranchIds = Branch::query()
            ->active()
            ->orderBy('id')
            ->pluck('id')
            ->filter(fn (int $branchId): bool => $branchId !== (int) $sourceProduct->branch_id);

        if ($missingBranchIds->isEmpty()) {
            return;
        }

        $replicationData = $data;
        unset($replicationData['branch_id']);
        $replicationData['slug'] = $this->resolveBaseSlug($sourceProduct->slug, (int) $sourceProduct->branch_id);
        $replicationData['code'] = $this->resolveBaseCode($manualCode, (int) $sourceProduct->branch_id);

        $photoPaths = $sourceProduct->photos()->pluck('image')->all();

        $this->createForBranches(
            $replicationData,
            $combinations,
            $mainPurchasePrice,
            $mainSalePrice,
            $mainInitialStock,
            $photoPaths,
            $productGroupId,
            $missingBranchIds,
        );
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function syncGroupCatalog(Product $sourceProduct, array $data): void
    {
        if ($sourceProduct->product_group_id === null) {
            return;
        }

        $sharedFields = [
            'name',
            'purchase_price',
            'sale_price',
            'discount_price',
            'tags',
            'description',
            'delivery_info',
            'youtube_link',
            'visible',
            'status',
            'image',
            'chest_size_image',
        ];

        foreach ($this->siblings($sourceProduct) as $sibling) {
            if ($sibling->is($sourceProduct)) {
                continue;
            }

            $branchData = $this->mapBranchCatalogFields($data, (int) $sibling->branch_id);

            $sibling->update(collect($branchData)->only($sharedFields)->all());
        }
    }

    public function mainSiblingInGroup(Product $product): ?Product
    {
        return $this->siblings($product)
            ->first(fn (Product $sibling): bool => Branch::isMainBranch($sibling->branch_id));
    }

    /** @return Collection<int, Product> */
    public function siblings(Product $product): Collection
    {
        if ($product->product_group_id === null) {
            return collect([$product]);
        }

        return Product::query()
            ->where('product_group_id', $product->product_group_id)
            ->get();
    }

    /**
     * @param  list<string>|null  $tagNames
     */
    private function ensureTagRecordsForBranch(?array $tagNames, int $branchId): void
    {
        foreach (collect($tagNames)->filter(fn ($name) => filled($name))->unique() as $name) {
            Tag::query()->firstOrCreate(
                [
                    'branch_id' => $branchId,
                    'name' => (string) $name,
                    'parent_id' => null,
                ],
                ['status' => CommonStatus::Active],
            );
        }
    }

    private function resolveBranchCode(?string $manualCode, int $branchId): ?string
    {
        if ($manualCode === null) {
            return null;
        }

        if (Branch::isMainBranch($branchId)) {
            return $manualCode;
        }

        return $manualCode.'-B'.$branchId;
    }

    private function resolveBranchSlug(string $baseSlug, int $branchId): string
    {
        if (Branch::isMainBranch($branchId)) {
            return $baseSlug;
        }

        return $baseSlug.'-b'.$branchId;
    }

    private function resolveBaseSlug(string $slug, int $branchId): string
    {
        if (Branch::isMainBranch($branchId)) {
            return $slug;
        }

        $suffix = '-b'.$branchId;

        if (Str::endsWith($slug, $suffix)) {
            return Str::beforeLast($slug, $suffix);
        }

        return $slug;
    }

    private function resolveBaseCode(?string $code, int $branchId): ?string
    {
        if ($code === null || Branch::isMainBranch($branchId)) {
            return $code;
        }

        $suffix = '-B'.$branchId;

        if (Str::endsWith($code, $suffix)) {
            return Str::beforeLast($code, $suffix);
        }

        return $code;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function mapBranchCatalogFields(array $data, int $branchId): array
    {
        $mapped = $data;

        $mapped['category_id'] = $this->catalogReplication->mapIdForBranch(
            Category::class,
            isset($data['category_id']) ? (int) $data['category_id'] : null,
            $branchId,
        );
        $mapped['brand_id'] = $this->catalogReplication->mapIdForBranch(
            Brand::class,
            isset($data['brand_id']) ? (int) $data['brand_id'] : null,
            $branchId,
        );
        $mapped['unit_id'] = $this->catalogReplication->mapIdForBranch(
            Unit::class,
            isset($data['unit_id']) ? (int) $data['unit_id'] : null,
            $branchId,
        );
        $mapped['warranty_id'] = $this->catalogReplication->mapIdForBranch(
            Warranty::class,
            isset($data['warranty_id']) ? (int) ($data['warranty_id'] ?? null) : null,
            $branchId,
        );

        if (! empty($data['colors'])) {
            $mapped['colors'] = $this->catalogReplication->mapIdsForBranch(
                Color::class,
                $data['colors'],
                $branchId,
            );
        }

        if (! empty($data['sizes'])) {
            $mapped['sizes'] = $this->catalogReplication->mapIdsForBranch(
                Size::class,
                $data['sizes'],
                $branchId,
            );
        }

        return $mapped;
    }

    private function productHasBranchActivity(Product $product): bool
    {
        return $product->purchaseProducts()->exists()
            || $product->sellProducts()->exists()
            || OnlineOrderProduct::query()->where('product_id', $product->id)->exists();
    }

    private function deleteCatalogProduct(Product $product): void
    {
        $product->loadMissing('photos');

        foreach ($product->photos as $photo) {
            Storage::disk('public')->delete($photo->image);
        }

        if ($product->image) {
            Storage::disk('public')->delete($product->image);
        }

        if ($product->chest_size_image) {
            Storage::disk('public')->delete($product->chest_size_image);
        }

        $product->delete();
    }
}
