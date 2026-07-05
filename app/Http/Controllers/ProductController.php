<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ScopesProductStockListing;
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
use App\Services\BarcodeService;
use App\Services\EcommerceBranchService;
use App\Services\ProductBranchReplicationService;
use App\Services\ProductDeletionService;
use App\Services\ProductInitialStockService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Unique;
use Inertia\Inertia;
use Inertia\Response;

class ProductController extends Controller
{
    use ScopesProductStockListing;

    public function __construct(
        private ProductBranchReplicationService $productReplication,
        private ProductDeletionService $productDeletion,
        private ProductInitialStockService $initialStock,
        private BarcodeService $barcodes,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('product.view');

        $listBranchId = $this->resolveProductListBranchId($request);
        $user = Auth::user();
        $canFilterProductsByBranch = $user?->branch_id === null;
        $usesAdminPanel = $user?->usesAdminPanel() ?? false;
        $mainBranchId = Branch::resolveMainBranchId();
        $showSelectedBranchColumn = $canFilterProductsByBranch && ($listBranchId === null || $listBranchId === $mainBranchId);

        $products = Product::query()
            ->active()
            ->when($listBranchId !== null, fn ($q) => $q->where('branch_id', $listBranchId))
            ->when($usesAdminPanel, fn ($q) => $q->visibleInMainCatalog())
            ->with([
                'category',
                'brand',
                ...($showSelectedBranchColumn ? ['selectedBranch:id,name'] : []),
                'variations' => fn ($q) => $this->scopeProductListVariations($q, $listBranchId),
            ])
            ->tap(fn ($q) => $this->applyProductListStockAggregates($q, $listBranchId))
            ->when($request->search, fn ($q, $s) => $q->where(function ($q) use ($s) {
                $q->where('name', 'like', "%{$s}%")
                    ->orWhere('code', 'like', "%{$s}%")
                    ->orWhereHas('brand', fn ($q) => $q->where('name', 'like', "%{$s}%"))
                    ->orWhereHas('category', fn ($q) => $q->where('name', 'like', "%{$s}%"))
                    ->orWhereJsonContains('tags', $s);
            }))
            ->when($request->category_id, fn ($q, $c) => $q->where('category_id', $c))
            ->when($request->brand_id, fn ($q, $b) => $q->where('brand_id', $b))
            ->when($request->tag, fn ($q, $t) => $q->whereJsonContains('tags', $t))
            ->latest()
            ->paginate(10)
            ->withQueryString()
            ->through(function (Product $product): Product {
                if ($product->source_branch_id !== null) {
                    $product->setAttribute(
                        'submission_stock_summary',
                        $product->submissionBranchStockSummary(),
                    );
                }

                return $product;
            });

        $pendingReceiveProducts = $usesAdminPanel
            ? Product::query()
                ->active()
                ->where('branch_id', $mainBranchId)
                ->pendingMainReceive()
                ->with(['sourceBranch:id,name'])
                ->latest()
                ->get()
                ->map(fn (Product $product): array => [
                    'id' => $product->id,
                    'slug' => $product->slug,
                    'name' => $product->name,
                    'code' => $product->code,
                    'branch_name' => $product->sourceBranch?->name,
                    'stock_summary' => $product->submissionBranchStockSummary(),
                    'created_at' => $product->created_at?->toIso8601String(),
                ])
                ->values()
                ->all()
            : [];

        return Inertia::render('admin/product/index', [
            'products' => $products,
            'pendingReceiveProducts' => $pendingReceiveProducts,
            'mainBranchId' => $mainBranchId,
            'showSelectedBranchColumn' => $showSelectedBranchColumn,
            'filters' => array_merge(
                $request->only('search', 'category_id', 'brand_id', 'tag'),
                $canFilterProductsByBranch ? [
                    'branch_id' => $request->input('branch_id', (string) $mainBranchId),
                ] : [],
            ),
            'branches' => $canFilterProductsByBranch ? Branch::active()->orderBy('name')->pluck('name', 'id') : [],
            'categories' => Category::forCatalogPanel()->active()->pluck('name', 'id'),
            'brands' => Brand::forCatalogPanel()->active()->pluck('name', 'id'),
            'tags' => Tag::selectableForProduct()
                ->with('parent:id,name')
                ->get(['id', 'name', 'parent_id'])
                ->sortBy(fn (Tag $tag): string => ($tag->parent?->name ?? $tag->name).' '.$tag->name)
                ->values()
                ->map(fn (Tag $tag): array => [
                    'value' => $tag->name,
                    'label' => $tag->parent_id && $tag->parent
                        ? "{$tag->parent->name} › {$tag->name}"
                        : $tag->name,
                ])
                ->all(),
        ]);
    }

    public function create(): Response
    {
        $this->authorize('product.create');

        return Inertia::render('admin/product/create', $this->formData());
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('product.create');

        $rawCombinations = $request->input('combinations', []);
        $hasVariations = ! empty($rawCombinations);
        $variantsRequested = $request->boolean('has_variants');

        $allCombosHavePrices = $hasVariations && collect($rawCombinations)
            ->every(fn ($c) => isset($c['sale_price']) && (string) $c['sale_price'] !== ''
                && isset($c['purchase_price']) && (string) $c['purchase_price'] !== '');

        $priceRequired = ! $hasVariations || ! $allCombosHavePrices;

        $data = $request->validate([
            'has_variants' => ['nullable', 'boolean'],
            'branch_id' => ['nullable', 'integer', Rule::exists('branches', 'id')],
            'category_id' => ['required', Rule::exists('categories', 'id')],
            'brand_id' => ['required', Rule::exists('brands', 'id')],
            'unit_id' => ['required', Rule::exists('units', 'id')],
            'warranty_id' => ['nullable', Rule::exists('warranties', 'id')],
            'color_ids' => ['nullable', 'array'],
            'color_ids.*' => [Rule::exists('colors', 'id')],
            'size_ids' => ['nullable', 'array'],
            'size_ids.*' => [Rule::exists('sizes', 'id')],
            'name' => ['required', 'string', 'max:255', 'unique:products,name'],
            'code' => [
                'nullable',
                'string',
                'max:'.BarcodeService::MAX_LENGTH,
                $this->barcodeUniqueRule(),
            ],
            'purchase_price' => $priceRequired ? ['required', 'numeric', 'min:0'] : ['nullable', 'numeric', 'min:0'],
            'sale_price' => $priceRequired ? ['required', 'numeric', 'min:0'] : ['nullable', 'numeric', 'min:0'],
            'discount_price' => ['nullable', 'numeric'],
            'initial_stock' => ['nullable', 'integer', 'min:0'],
            'tags' => ['nullable', 'array'],
            'visible' => ['nullable', 'in:yes,no'],
            'status' => ['nullable', 'in:0,1'],
            'description' => ['nullable', 'string'],
            'delivery_info' => ['nullable', 'string'],
            'youtube_link' => ['nullable', 'string', 'max:255'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:3072'],
            'chest_size_image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:3072'],
            'photos' => ['nullable', 'array'],
            'photos.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:3072'],
            'combinations' => [
                Rule::requiredIf($variantsRequested),
                'nullable',
                'array',
                Rule::when($variantsRequested, ['min:1']),
            ],
            'combinations.*.variant' => ['required_with:combinations', 'string', 'max:255'],
            'combinations.*.variation_data' => ['nullable', 'array'],
            'combinations.*.sale_price' => ['nullable', 'numeric', 'min:0'],
            'combinations.*.purchase_price' => ['nullable', 'numeric', 'min:0'],
            'combinations.*.sku' => ['nullable', 'string', 'max:'.BarcodeService::MAX_LENGTH],
            'combinations.*.stock' => ['nullable', 'integer', 'min:0'],
        ]);

        if ($variantsRequested && empty($data['combinations'] ?? [])) {
            return back()
                ->withInput()
                ->withErrors([
                    'combinations' => 'Click "Build Combinations" before saving.',
                ]);
        }

        $combinations = $data['combinations'] ?? [];
        unset($data['combinations'], $data['has_variants']);

        $data = $this->normalizeProductColorAndSizeIds($data);

        $mainPurchasePrice = $data['purchase_price'] ?? 0;
        $mainSalePrice = $data['sale_price'] ?? 0;
        $mainInitialStock = (int) ($data['initial_stock'] ?? 0);
        unset($data['initial_stock']);

        $data['selected_branch_id'] = filled($data['branch_id'] ?? null) ? (int) $data['branch_id'] : null;

        // For variation products, don't persist main prices on the product row
        if ($hasVariations) {
            $data['purchase_price'] = 0;
            $data['sale_price'] = 0;
            $data['colors'] = null;
            $data['sizes'] = null;
            $data['code'] = null;
        }

        DB::transaction(function () use ($request, $data, $combinations, $mainPurchasePrice, $mainSalePrice, $mainInitialStock) {
            if ($request->hasFile('image')) {
                $data['image'] = $request->file('image')->store('products', 'public');
            }

            if ($request->hasFile('chest_size_image')) {
                $data['chest_size_image'] = $request->file('chest_size_image')->store('products', 'public');
            }

            $data['visible'] = $this->resolveProductVisibility($data);
            $data['status'] = (int) ($data['status'] ?? 1);
            $data['discount_price'] = $data['discount_price'] ?? 0;

            if (blank(trim((string) ($data['code'] ?? '')))) {
                $data['code'] = null;
            }

            $photoPaths = [];

            if ($request->hasFile('photos')) {
                foreach ($request->file('photos') as $photo) {
                    $photoPaths[] = $photo->store('products/photos', 'public');
                }
            }

            $this->productReplication->createSingle(
                $data,
                $combinations,
                $mainPurchasePrice,
                $mainSalePrice,
                $mainInitialStock,
                $photoPaths,
            );
        });

        return redirect()->route('product.index')
            ->with('success', 'Product created successfully.');
    }

    public function receive(Product $product): RedirectResponse
    {
        $this->authorize('product.update');

        abort_unless(
            $product->isPendingMainReceive() && Branch::isMainBranch($product->branch_id),
            404,
        );

        $branchName = $product->sourceBranch?->name ?? 'Branch';

        $product->update(['received_at' => now()]);

        return redirect()->route('product.index')
            ->with('success', "Product received from {$branchName}.");
    }

    public function edit(Product $product): Response
    {
        $this->authorize('product.update');

        $product->load('photos', 'variations', 'initialStockRecord', 'category', 'brand', 'unit', 'warranty');

        return Inertia::render('admin/product/edit', [
            ...$this->formData(),
            'product' => $product,
            'formBranchId' => $this->productReplication->resolveFormBranchSelection($product),
            'variantsLocked' => $this->variantsAreLocked($product),
            'selectedColors' => Color::query()
                ->whereIn('id', $product->colors ?? [])
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn (Color $color): array => [
                    'id' => (string) $color->id,
                    'label' => $color->name,
                ])
                ->values()
                ->all(),
            'selectedSizes' => Size::query()
                ->whereIn('id', $product->sizes ?? [])
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn (Size $size): array => [
                    'id' => (string) $size->id,
                    'label' => $size->name,
                ])
                ->values()
                ->all(),
        ]);
    }

    public function update(Request $request, Product $product): RedirectResponse
    {
        $this->authorize('product.update');

        $variantsLocked = $this->variantsAreLocked($product);
        $rawCombinations = $variantsLocked ? [] : $request->input('combinations', []);
        $hasVariations = ! empty($rawCombinations);
        $variantsRequested = ! $variantsLocked && $request->boolean('has_variants');

        $allCombosHavePrices = $hasVariations && collect($rawCombinations)
            ->every(fn ($c) => isset($c['sale_price']) && (string) $c['sale_price'] !== ''
                && isset($c['purchase_price']) && (string) $c['purchase_price'] !== '');

        $priceRequired = ! $hasVariations || ! $allCombosHavePrices;

        $data = $request->validate([
            'has_variants' => ['nullable', 'boolean'],
            'branch_id' => ['nullable', 'integer', Rule::exists('branches', 'id')],
            'category_id' => ['required', Rule::exists('categories', 'id')],
            'brand_id' => ['required', Rule::exists('brands', 'id')],
            'unit_id' => ['required', Rule::exists('units', 'id')],
            'warranty_id' => ['nullable', Rule::exists('warranties', 'id')],
            'color_ids' => ['nullable', 'array'],
            'color_ids.*' => [Rule::exists('colors', 'id')],
            'size_ids' => ['nullable', 'array'],
            'size_ids.*' => [Rule::exists('sizes', 'id')],
            'name' => ['required', 'string', 'max:255', $this->productNameUniqueRule($product)],
            'code' => [
                'nullable',
                'string',
                'max:'.BarcodeService::MAX_LENGTH,
                $this->barcodeUniqueRule($product),
            ],
            'purchase_price' => $priceRequired ? ['required', 'numeric', 'min:0'] : ['nullable', 'numeric', 'min:0'],
            'sale_price' => $priceRequired ? ['required', 'numeric', 'min:0'] : ['nullable', 'numeric', 'min:0'],
            'discount_price' => ['nullable', 'numeric'],
            'initial_stock' => ['nullable', 'integer', 'min:0'],
            'tags' => ['nullable', 'array'],
            'visible' => ['nullable', 'in:yes,no'],
            'status' => ['nullable', 'in:0,1'],
            'description' => ['nullable', 'string'],
            'delivery_info' => ['nullable', 'string'],
            'youtube_link' => ['nullable', 'string', 'max:255'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:3072'],
            'chest_size_image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:3072'],
            'photos' => ['nullable', 'array'],
            'photos.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:3072'],
            'combinations' => $variantsLocked ? ['nullable'] : [
                Rule::requiredIf($variantsRequested),
                'nullable',
                'array',
                Rule::when($variantsRequested, ['min:1']),
            ],
            'combinations.*.id' => ['nullable', 'integer'],
            'combinations.*.variant' => ['required_with:combinations', 'string', 'max:255'],
            'combinations.*.variation_data' => ['nullable', 'array'],
            'combinations.*.sale_price' => ['nullable', 'numeric', 'min:0'],
            'combinations.*.purchase_price' => ['nullable', 'numeric', 'min:0'],
            'combinations.*.sku' => ['nullable', 'string', 'max:'.BarcodeService::MAX_LENGTH],
            'combinations.*.stock' => ['nullable', 'integer', 'min:0'],
        ]);

        if ($variantsRequested && empty($data['combinations'] ?? [])) {
            return back()
                ->withInput()
                ->withErrors([
                    'combinations' => 'Click "Build Combinations" before saving.',
                ]);
        }

        $combinations = $variantsLocked ? [] : ($data['combinations'] ?? []);
        unset($data['combinations'], $data['has_variants']);

        $data = $this->normalizeProductColorAndSizeIds($data);

        $mainPurchasePrice = $data['purchase_price'] ?? 0;
        $mainSalePrice = $data['sale_price'] ?? 0;
        $mainInitialStock = (int) ($data['initial_stock'] ?? 0);
        unset($data['initial_stock']);

        if ($request->exists('branch_id')) {
            $data['selected_branch_id'] = filled($data['branch_id']) ? (int) $data['branch_id'] : null;
        }

        if ($hasVariations) {
            $data['purchase_price'] = 0;
            $data['sale_price'] = 0;
            $data['colors'] = null;
            $data['sizes'] = null;
            $data['code'] = null;
        } elseif (blank(trim((string) ($data['code'] ?? '')))) {
            $data['code'] = $product->code;
        }

        DB::transaction(function () use ($request, $data, $product, $combinations, $hasVariations, $variantsLocked, $mainPurchasePrice, $mainSalePrice, $mainInitialStock) {
            if ($request->hasFile('image')) {
                if ($product->image) {
                    Storage::disk('public')->delete($product->image);
                }
                $data['image'] = $request->file('image')->store('products', 'public');
            } else {
                unset($data['image']);
            }

            if ($request->hasFile('chest_size_image')) {
                if ($product->chest_size_image) {
                    Storage::disk('public')->delete($product->chest_size_image);
                }
                $data['chest_size_image'] = $request->file('chest_size_image')->store('products', 'public');
            } else {
                unset($data['chest_size_image']);
            }

            $data['visible'] = $this->resolveProductVisibility($data, $product);
            $data['status'] = (int) ($data['status'] ?? 1);
            $data['discount_price'] = $data['discount_price'] ?? 0;

            $branchSelectionProvided = $request->exists('branch_id');
            $selectedBranchId = filled($data['branch_id'] ?? null) ? (int) $data['branch_id'] : null;
            $requestedAllBranches = $branchSelectionProvided && blank($data['branch_id'] ?? null);
            $previousSelection = $this->productReplication->resolveStoredSelection($product);

            if ($branchSelectionProvided) {
                $data['selected_branch_id'] = $selectedBranchId;
            }

            $expandingToAllBranches = $requestedAllBranches
                && $product->product_group_id === null
                && $product->branch_id !== null
                && $previousSelection === null
                && ! Branch::isMainBranch((int) $product->branch_id);
            $isBranchSelectionChange = $branchSelectionProvided
                && ! $requestedAllBranches
                && $selectedBranchId !== null
                && $selectedBranchId !== $previousSelection;

            unset($data['branch_id']);

            if (! Branch::isMainBranch((int) $product->branch_id)) {
                unset($data['selected_branch_id']);
            }

            $product->update($data);

            if ($branchSelectionProvided && ! Branch::isMainBranch((int) $product->branch_id)) {
                $this->productReplication->mainSiblingInGroup($product)?->update([
                    'selected_branch_id' => $selectedBranchId,
                ]);
            }

            if ($request->hasFile('photos')) {
                foreach ($request->file('photos') as $photo) {
                    $path = $photo->store('products/photos', 'public');
                    ProductPhoto::create(['product_id' => $product->id, 'image' => $path]);
                }
            }

            if ($requestedAllBranches) {
                $anchorProduct = $this->productReplication->mainSiblingInGroup($product);

                if ($anchorProduct === null && Branch::isMainBranch((int) $product->branch_id)) {
                    $anchorProduct = $product;
                }

                if ($anchorProduct !== null) {
                    if (! $variantsLocked && $hasVariations) {
                        $this->syncProductVariations($anchorProduct, $combinations, $mainPurchasePrice, $mainSalePrice, $mainInitialStock);
                    }

                    $this->productReplication->expandGroupToAllBranches(
                        $anchorProduct->fresh(),
                        $data,
                        $combinations,
                        (float) $mainPurchasePrice,
                        (float) $mainSalePrice,
                        $mainInitialStock,
                    );

                    $this->productReplication->syncGroupCatalog($anchorProduct->fresh(), $data);

                    if (! $variantsLocked && ! $hasVariations) {
                        $this->initialStock->syncNonVariant(
                            $anchorProduct->fresh(),
                            $mainInitialStock,
                            (float) $mainPurchasePrice,
                        );
                    }

                    $this->syncProductBarcode($anchorProduct->fresh(), $hasVariations);

                    return;
                }

                if ($expandingToAllBranches) {
                    if (! $variantsLocked && $hasVariations) {
                        $this->syncProductVariations($product, $combinations, $mainPurchasePrice, $mainSalePrice, $mainInitialStock);
                    }

                    $this->productReplication->expandToAllBranches(
                        $product->fresh(),
                        $data,
                        $combinations,
                        (float) $mainPurchasePrice,
                        (float) $mainSalePrice,
                        $mainInitialStock,
                    );

                    $this->syncProductBarcode($product->fresh(), $hasVariations);

                    return;
                }

                return;
            }

            if (! $variantsLocked) {
                if ($hasVariations) {
                    $this->syncProductVariations($product, $combinations, $mainPurchasePrice, $mainSalePrice, $mainInitialStock);
                } elseif ($product->variations()->exists()) {
                    $this->clearProductVariations($product);
                } else {
                    $this->initialStock->syncNonVariant(
                        $product->fresh(),
                        $mainInitialStock,
                        (float) $mainPurchasePrice,
                    );
                }
            }

            if ($isBranchSelectionChange) {
                $this->productReplication->applyBranchSelectionChange(
                    $product->fresh(),
                    $selectedBranchId,
                    $data,
                    $combinations,
                    (float) $mainPurchasePrice,
                    (float) $mainSalePrice,
                    $mainInitialStock,
                );

                $this->syncProductBarcode($product->fresh(), $hasVariations);

                return;
            }

            $this->syncProductBarcode($product->fresh(), $hasVariations);
        });

        return redirect()->route('product.index')
            ->with('success', 'Product updated successfully.');
    }

    public function destroy(Product $product): RedirectResponse
    {
        $this->authorize('product.delete');

        try {
            $result = $this->productDeletion->delete($product);
        } catch (\Throwable $e) {
            return back()->with(
                'error',
                $e instanceof \RuntimeException ? $e->getMessage() : 'Unable to remove product.',
            );
        }

        return redirect()->route('product.index')
            ->with('success', $result['message']);
    }

    private function variantsAreLocked(Product $product): bool
    {
        return $product->purchaseProducts()->exists() || $product->sellProducts()->exists();
    }

    /**
     * @param  array<int, array<string, mixed>>  $combinations
     */
    private function syncProductVariations(Product $product, array $combinations, float $mainPurchasePrice, float $mainSalePrice, int $mainInitialStock = 0): void
    {
        $combinations = $this->barcodes->normalizeCombinationsForBranch(
            (int) $product->branch_id,
            $combinations,
            $product->product_group_id,
            $product->id,
        );

        $keepIds = collect($combinations)->pluck('id')->filter()->map(fn ($id) => (int) $id)->values();

        $product->variations()
            ->whereNotIn('id', $keepIds)
            ->get()
            ->each(function (ProductVariation $variation): void {
                if ($variation->stock > 0) {
                    return;
                }

                $variation->delete();
            });

        foreach ($combinations as $combo) {
            $salePrice = (isset($combo['sale_price']) && (string) $combo['sale_price'] !== '')
                ? $combo['sale_price']
                : $mainSalePrice;

            $purchasePrice = (isset($combo['purchase_price']) && (string) $combo['purchase_price'] !== '')
                ? $combo['purchase_price']
                : $mainPurchasePrice;

            $stock = $this->initialStock->resolveComboStock($combo, $mainInitialStock);

            $variationData = $combo['variation_data'] ?? ['label' => $combo['variant']];

            if (! empty($combo['id'])) {
                $variation = $product->variations()->find((int) $combo['id']);

                if ($variation) {
                    $oldStock = (int) $variation->stock;
                    $sku = $this->barcodes->resolveVariationBarcode(
                        (int) $product->branch_id,
                        $combo['sku'],
                        $variation->id,
                        $product->id,
                        $product->product_group_id,
                    );

                    $variation->update([
                        'sku' => $sku,
                        'price' => $salePrice,
                        'purchase_price' => $purchasePrice,
                        'stock' => $stock,
                        'variation_data' => $variationData,
                    ]);

                    $stockDelta = $stock - $oldStock;

                    if ($stockDelta !== 0) {
                        $this->initialStock->postStockQuantityAdjustment(
                            $product,
                            $variation,
                            $stockDelta,
                            (float) $purchasePrice,
                            $product->name.' — '.($variationData['label'] ?? $combo['variant']),
                        );
                    }

                    Barcode::query()->updateOrCreate(
                        ['product_variation_id' => $variation->id],
                        [
                            'branch_id' => $product->branch_id,
                            'product_id' => $product->id,
                            'code' => $sku,
                            'name' => $product->name.' - '.($variationData['label'] ?? $combo['variant']),
                        ],
                    );
                }

                continue;
            }

            $sku = $this->barcodes->resolveVariationBarcode(
                (int) $product->branch_id,
                $combo['sku'],
                excludeProductId: $product->id,
                productGroupId: $product->product_group_id,
            );

            $variation = ProductVariation::create([
                'product_id' => $product->id,
                'branch_id' => $product->branch_id,
                'sku' => $sku,
                'price' => $salePrice,
                'purchase_price' => $purchasePrice,
                'stock' => 0,
                'variation_data' => $variationData,
            ]);

            $this->initialStock->applyVariationStockOnCreate($variation, $stock, (float) $purchasePrice);

            Barcode::create([
                'branch_id' => $product->branch_id,
                'product_id' => $product->id,
                'product_variation_id' => $variation->id,
                'code' => $sku,
                'name' => $product->name.' - '.$combo['variant'],
            ]);
        }
    }

    private function clearProductVariations(Product $product): void
    {
        $product->variations()
            ->get()
            ->each(function (ProductVariation $variation): void {
                if ($variation->stock > 0) {
                    return;
                }

                Barcode::query()->where('product_variation_id', $variation->id)->delete();
                $variation->delete();
            });

        if ($product->code && ! Barcode::query()->where('product_id', $product->id)->whereNull('product_variation_id')->exists()) {
            Barcode::create([
                'branch_id' => $product->branch_id,
                'product_id' => $product->id,
                'product_variation_id' => null,
                'code' => $product->code,
                'name' => $product->name,
            ]);
        }
    }

    private function syncProductBarcode(Product $product, bool $hasVariations): void
    {
        if ($hasVariations || $product->variations()->exists()) {
            Barcode::query()
                ->where('product_id', $product->id)
                ->whereNull('product_variation_id')
                ->delete();

            return;
        }

        if (blank($product->code)) {
            Barcode::query()
                ->where('product_id', $product->id)
                ->whereNull('product_variation_id')
                ->delete();

            return;
        }

        Barcode::query()->updateOrCreate(
            [
                'product_id' => $product->id,
                'product_variation_id' => null,
            ],
            [
                'branch_id' => $product->branch_id,
                'code' => $product->code,
                'name' => $product->name,
            ],
        );
    }

    /** @return array<string, mixed> */
    private function normalizeProductColorAndSizeIds(array $data): array
    {
        $data['colors'] = collect($data['color_ids'] ?? [])
            ->filter(fn ($id) => filled($id))
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        $data['sizes'] = collect($data['size_ids'] ?? [])
            ->filter(fn ($id) => filled($id))
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        unset($data['color_ids'], $data['size_ids']);

        return $data;
    }

    private function productNameUniqueRule(Product $product): Unique
    {
        $rule = Rule::unique('products', 'name')->ignore($product->id);

        if ($product->product_group_id !== null) {
            $rule->where(function ($query) use ($product) {
                $query->where(function ($query) use ($product) {
                    $query->whereNull('product_group_id')
                        ->orWhere('product_group_id', '!=', $product->product_group_id);
                });
            });
        }

        return $rule;
    }

    private function barcodeUniqueRule(?Product $product = null): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail) use ($product): void {
            if (blank($value)) {
                return;
            }

            if (! $this->barcodes->codeIsAvailableGlobally(
                (string) $value,
                $product?->product_group_id,
                $product?->id,
            )) {
                $fail('This barcode is already used by another product.');
            }
        };
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function resolveProductVisibility(array $data, ?Product $product = null): string
    {
        $ecommerceBranchId = EcommerceBranchService::resolveIdStatic();
        $branchId = $data['branch_id'] ?? $product?->branch_id ?? Auth::user()?->branch_id;

        if (
            $ecommerceBranchId === null
            || $branchId === null
            || (int) $branchId !== $ecommerceBranchId
            || ! Auth::user()?->can('product.visible-on-store')
        ) {
            return $product?->visible ?? 'no';
        }

        return $data['visible'] ?? ($product?->visible ?? 'no');
    }

    /** @return array<string, mixed> */
    private function formData(): array
    {
        $defaultCatalogBranchId = Branch::resolveAdminCatalogBranchId();
        $showBranchField = Auth::user()?->usesAdminPanel() ?? false;

        return [
            'defaultCatalogBranchId' => $defaultCatalogBranchId,
            'ecommerceBranchId' => EcommerceBranchService::resolveIdStatic(),
            'showBranchField' => $showBranchField,
            'categories' => Category::forCatalogPanel()->active()->orderBy('name')->pluck('name', 'id'),
            'brands' => Brand::forCatalogPanel()->active()->orderBy('name')->pluck('name', 'id'),
            'units' => Unit::forCatalogPanel()->active()->orderBy('name')->pluck('name', 'id'),
            'warranties' => Warranty::forCatalogPanel()->active()->orderBy('name')->pluck('name', 'id'),
            'branches' => $showBranchField ? Branch::active()->orderBy('name')->pluck('name', 'id') : [],
            'colorOptions' => Color::forCatalogPanel()->active()->orderBy('name')->get(['id', 'name'])
                ->map(fn (Color $color): array => [
                    'value' => $color->name,
                    'label' => $color->name,
                    'id' => (string) $color->id,
                ])
                ->all(),
            'sizeOptions' => Size::forCatalogPanel()->active()->orderBy('name')->get(['id', 'name'])
                ->map(fn (Size $size): array => [
                    'value' => $size->name,
                    'label' => $size->name,
                    'id' => (string) $size->id,
                ])
                ->all(),
            'tagOptions' => Tag::query()
                ->selectableForProduct()
                ->with('parent:id,name')
                ->get(['id', 'name', 'parent_id'])
                ->sortBy(fn (Tag $tag): string => ($tag->parent?->name ?? $tag->name).' '.$tag->name)
                ->values()
                ->map(fn (Tag $tag): array => [
                    'value' => $tag->name,
                    'label' => $tag->parent_id && $tag->parent
                        ? "{$tag->parent->name} › {$tag->name}"
                        : $tag->name,
                ])
                ->all(),
        ];
    }
}
