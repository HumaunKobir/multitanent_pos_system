<?php

namespace App\Http\Controllers;

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
use App\Models\Variation;
use App\Models\Warranty;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ProductController extends Controller
{
    public function index(Request $request): Response
    {
        $products = Product::ownBranch()
            ->with(['category', 'brand'])
            ->withSum('variations', 'stock')
            ->withSum('batches', 'available')
            ->when($request->search, fn ($q, $s) => $q->where(function ($q) use ($s) {
                $q->where('name', 'like', "%{$s}%")
                    ->orWhere('code', 'like', "%{$s}%");
            }))
            ->when($request->category_id, fn ($q, $c) => $q->where('category_id', $c))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('admin/product/index', [
            'products' => $products,
            'filters' => $request->only('search', 'category_id'),
            'categories' => Category::active()->pluck('name', 'id'),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/product/create', $this->formData());
    }

    public function store(Request $request): RedirectResponse
    {
        $rawCombinations = $request->input('combinations', []);
        $hasVariations = ! empty($rawCombinations);

        $allCombosHavePrices = $hasVariations && collect($rawCombinations)
            ->every(fn ($c) => isset($c['sale_price']) && (string) $c['sale_price'] !== ''
                && isset($c['purchase_price']) && (string) $c['purchase_price'] !== '');

        $priceRequired = ! $hasVariations || ! $allCombosHavePrices;

        $data = $request->validate([
            'branch_id' => ['nullable', 'integer', Rule::exists('branches', 'id')],
            'category_id' => ['required', Rule::exists('categories', 'id')],
            'brand_id' => ['required', Rule::exists('brands', 'id')],
            'unit_id' => ['required', Rule::exists('units', 'id')],
            'warranty_id' => ['nullable', Rule::exists('warranties', 'id')],
            'name' => ['required', 'string', 'max:255', 'unique:products,name'],
            'code' => $hasVariations ? ['nullable', 'string', 'max:100'] : ['required', 'string', 'max:100', 'unique:products,code'],
            'purchase_price' => $priceRequired ? ['required', 'numeric', 'min:0'] : ['nullable', 'numeric', 'min:0'],
            'sale_price' => $priceRequired ? ['required', 'numeric', 'min:0'] : ['nullable', 'numeric', 'min:0'],
            'discount_price' => ['nullable', 'numeric'],
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
            'combinations' => ['nullable', 'array'],
            'combinations.*.variant' => ['required_with:combinations', 'string', 'max:255'],
            'combinations.*.sale_price' => ['nullable', 'numeric', 'min:0'],
            'combinations.*.purchase_price' => ['nullable', 'numeric', 'min:0'],
            'combinations.*.sku' => ['required_with:combinations', 'string', 'max:255'],
            'combinations.*.stock' => ['required_with:combinations', 'integer', 'min:0'],
        ]);

        $combinations = $data['combinations'] ?? [];
        unset($data['combinations']);

        $mainPurchasePrice = $data['purchase_price'] ?? 0;
        $mainSalePrice = $data['sale_price'] ?? 0;

        // For variation products, don't persist main prices on the product row
        if ($hasVariations) {
            $data['purchase_price'] = 0;
            $data['sale_price'] = 0;
        }

        DB::transaction(function () use ($request, $data, $combinations, $mainPurchasePrice, $mainSalePrice) {
            if ($request->hasFile('image')) {
                $data['image'] = $request->file('image')->store('products', 'public');
            }

            if ($request->hasFile('chest_size_image')) {
                $data['chest_size_image'] = $request->file('chest_size_image')->store('products', 'public');
            }

            $data['visible'] = $data['visible'] ?? 'yes';
            $data['status'] = (int) ($data['status'] ?? 1);
            $data['discount_price'] = $data['discount_price'] ?? 0;

            $product = Product::create($data);

            if ($request->hasFile('photos')) {
                foreach ($request->file('photos') as $photo) {
                    $path = $photo->store('products/photos', 'public');
                    ProductPhoto::create(['product_id' => $product->id, 'image' => $path]);
                }
            }

            foreach ($combinations as $combo) {
                // Use per-combo price if provided, otherwise fall back to main prices
                $salePrice = (isset($combo['sale_price']) && (string) $combo['sale_price'] !== '')
                    ? $combo['sale_price']
                    : $mainSalePrice;

                $purchasePrice = (isset($combo['purchase_price']) && (string) $combo['purchase_price'] !== '')
                    ? $combo['purchase_price']
                    : $mainPurchasePrice;

                ProductVariation::create([
                    'product_id' => $product->id,
                    'branch_id' => $product->branch_id,
                    'sku' => $combo['sku'],
                    'price' => $salePrice,
                    'purchase_price' => $purchasePrice,
                    'stock' => (int) $combo['stock'],
                    'variation_data' => ['label' => $combo['variant']],
                ]);
            }
        });

        return redirect()->route('product.index')
            ->with('success', 'Product created successfully.');
    }

    public function edit(Product $product): Response
    {
        $product->load('photos', 'variations');

        return Inertia::render('admin/product/edit', [
            ...$this->formData(),
            'product' => $product,
        ]);
    }

    public function update(Request $request, Product $product): RedirectResponse
    {
        $data = $request->validate([
            'branch_id' => ['nullable', 'integer', Rule::exists('branches', 'id')],
            'category_id' => ['required', Rule::exists('categories', 'id')],
            'brand_id' => ['required', Rule::exists('brands', 'id')],
            'unit_id' => ['required', Rule::exists('units', 'id')],
            'warranty_id' => ['nullable', Rule::exists('warranties', 'id')],
            'name' => ['required', 'string', 'max:255', Rule::unique('products', 'name')->ignore($product->id)],
            'code' => ['required', 'string', 'max:100', Rule::unique('products', 'code')->ignore($product->id)],
            'purchase_price' => ['required', 'numeric', 'min:0'],
            'sale_price' => ['required', 'numeric', 'min:0'],
            'discount_price' => ['nullable', 'numeric'],
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
        ]);

        DB::transaction(function () use ($request, $data, $product) {
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

            $data['visible'] = $data['visible'] ?? 'yes';
            $data['status'] = (int) ($data['status'] ?? 1);
            $data['discount_price'] = $data['discount_price'] ?? 0;

            $product->update($data);

            if ($request->hasFile('photos')) {
                foreach ($request->file('photos') as $photo) {
                    $path = $photo->store('products/photos', 'public');
                    ProductPhoto::create(['product_id' => $product->id, 'image' => $path]);
                }
            }
        });

        return redirect()->route('product.index')
            ->with('success', 'Product updated successfully.');
    }

    public function destroy(Product $product): RedirectResponse
    {
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

        return redirect()->route('product.index')
            ->with('success', 'Product deleted successfully.');
    }

    /** @return array<string, mixed> */
    private function formData(): array
    {
        return [
            'categories' => Category::active()->pluck('name', 'id'),
            'brands' => Brand::active()->pluck('name', 'id'),
            'units' => Unit::active()->pluck('name', 'id'),
            'warranties' => Warranty::active()->pluck('name', 'id'),
            'branches' => Branch::active()->orderBy('name')->pluck('name', 'id'),
            'variationNames' => Variation::where('status', 1)->pluck('name'),
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
