<?php

namespace App\Services;

use App\Enums\CommonStatus;
use App\Models\Barcode;
use App\Models\Branch;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Color;
use App\Models\Product;
use App\Models\ProductPhoto;
use App\Models\ProductVariation;
use App\Models\Size;
use App\Models\Tag;
use App\Models\Unit;
use App\Models\Warranty;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

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
        $created = [];

        foreach ($branchIds as $branchId) {
            $branchId = (int) $branchId;
            $branchData = $this->mapBranchCatalogFields($data, $branchId);
            $branchData['branch_id'] = $branchId;
            $branchData['product_group_id'] = $productGroupId;
            $branchData['slug'] = '';
            $branchData['code'] = $this->resolveBranchCode($manualCode, $branchId);

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
        $branchId = filled($data['branch_id'] ?? null) ? (int) $data['branch_id'] : null;

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

        $data['branch_id'] = $branchId;

        $product = Product::create($data);

        foreach ($photoPaths as $path) {
            ProductPhoto::create([
                'product_id' => $product->id,
                'image' => $path,
            ]);
        }

        if ($combinations === [] && $product->code) {
            Barcode::create([
                'branch_id' => $product->branch_id,
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
                'branch_id' => $product->branch_id,
                'sku' => $combo['sku'],
                'price' => $salePrice,
                'purchase_price' => $purchasePrice,
                'stock' => 0,
                'variation_data' => $combo['variation_data'] ?? ['label' => $combo['variant']],
            ]);

            $this->initialStock->applyVariationStockOnCreate($variation, $stock, (float) $purchasePrice);

            Barcode::create([
                'branch_id' => $product->branch_id,
                'product_id' => $product->id,
                'product_variation_id' => $variation->id,
                'code' => $combo['sku'],
                'name' => $product->name.' - '.$combo['variant'],
            ]);
        }

        return [$product];
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<int, array<string, mixed>>  $combinations
     */
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
        unset($replicationData['branch_id'], $replicationData['slug']);
        $replicationData['code'] = $manualCode;

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
}
