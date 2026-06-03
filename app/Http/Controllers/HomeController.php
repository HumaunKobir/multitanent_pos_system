<?php

namespace App\Http\Controllers;

use App\Enums\BlockType;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Collectioncategory;
use App\Models\Color;
use App\Models\ConfigDictionary;
use App\Models\Contact;
use App\Models\Product;
use App\Models\ProductSection;
use App\Models\Size;
use App\Models\Slider;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class HomeController extends Controller
{
    public function index(): Response
    {
        $sliders = Slider::active()
            ->orderBy('id', 'desc')
            ->get()
            ->map(fn (Slider $slider): array => [
                'id' => $slider->id,
                'name' => $slider->name,
                'image' => $slider->image
                    ? Storage::disk('public')->url($slider->image)
                    : null,
            ]);
        $collections = Collectioncategory::active()->get();
        $productSections = ProductSection::active()
            ->orderBy('serial')
            ->get()
            ->map(function (ProductSection $section) {
                return [
                    'id' => $section->id,
                    'name' => $section->name,
                    'description' => $section->description,
                    'button_text' => $section->button_text,
                    'block_per_line' => $section->block_per_line,
                    'layout_type' => $section->layout_type?->value,
                    'block_type' => $section->block_type?->value,
                    'images' => $section->product_images,
                    'products' => $section->block_type === BlockType::Item
                        ? $section->product_items->map(fn (Product $p) => $this->formatProduct($p))->values()
                        : [],
                ];
            });

        return Inertia::render('frontend/home', [
            'sliders' => $sliders,
            'collections' => $collections,
            'productSections' => $productSections,
            'siteName' => ConfigDictionary::get('website_name', 'Coolness Point'),
            'topNotice' => ConfigDictionary::get('topnotice1'),
        ]);
    }

    public function show(string $slug): Response
    {
        $product = Product::with(['photos', 'variations', 'category', 'brand'])
            ->where('slug', $slug)
            ->where('status', 1)
            ->firstOrFail();

        return Inertia::render('frontend/single-product', [
            'product' => $this->formatProduct($product, true),
        ]);
    }

    public function sectionProducts(int $id, Request $request): Response
    {
        $section = ProductSection::findOrFail($id);
        $productIds = $section->items ?? [];

        $query = Product::with(['photos'])->withCount('variations')
            ->where('status', 1)
            ->where('visible', 'yes')
            ->whereIn('id', $productIds);

        $query = $this->applyFilters($query, $request);

        $products = $query->paginate(12)->through(fn ($p) => $this->formatProduct($p));

        return Inertia::render('frontend/section-products', [
            'section' => [
                'id' => $section->id,
                'name' => $section->name,
                'description' => $section->description,
            ],
            'products' => $products,
            'filters' => $request->only(['min_price', 'max_price', 'brands', 'colors', 'sizes', 'sort_by']),
            'allBrands' => Brand::active()->pluck('name'),
            'allColors' => Color::active()->pluck('name'),
            'allSizes' => Size::active()->pluck('name'),
        ]);
    }

    public function collectionProducts(string $name, Request $request): Response
    {
        $query = Product::with(['photos'])->withCount('variations')
            ->where('status', 1)
            ->where('visible', 'yes')
            ->whereJsonContains('tags', $name);

        $query = $this->applyFilters($query, $request);

        $products = $query->paginate(12)->through(fn ($p) => $this->formatProduct($p));

        return Inertia::render('frontend/collection-products', [
            'collectionName' => $name,
            'products' => $products,
            'filters' => $request->only(['min_price', 'max_price', 'brands', 'colors', 'sizes', 'sort_by']),
            'allBrands' => Brand::active()->pluck('name'),
            'allColors' => Color::active()->pluck('name'),
            'allSizes' => Size::active()->pluck('name'),
        ]);
    }

    public function categoryProducts(int $id, Request $request): Response
    {
        $category = Category::findOrFail($id);
        $query = Product::with(['photos'])->withCount('variations')
            ->where('status', 1)
            ->where('visible', 'yes')
            ->where('category_id', $id);

        $query = $this->applyFilters($query, $request);

        $products = $query->paginate(12)->through(fn ($p) => $this->formatProduct($p));

        return Inertia::render('frontend/category-products', [
            'category' => $category,
            'products' => $products,
            'filters' => $request->only(['min_price', 'max_price', 'brands', 'colors', 'sizes', 'sort_by']),
            'allBrands' => Brand::active()->pluck('name'),
            'allColors' => Color::active()->pluck('name'),
            'allSizes' => Size::active()->pluck('name'),
        ]);
    }

    public function search(Request $request): Response
    {
        $query = $request->get('q', '');
        $products = Product::with(['photos'])->withCount('variations')
            ->where('status', 1)
            ->where('visible', 'yes')
            ->where(function ($q) use ($query) {
                $q->where('name', 'like', '%'.$query.'%')
                    ->orWhere('code', 'like', '%'.$query.'%');
            })
            ->paginate(12)
            ->through(fn ($p) => $this->formatProduct($p));

        return Inertia::render('frontend/search', [
            'query' => $query,
            'products' => $products,
        ]);
    }

    public function contact(): Response
    {
        return Inertia::render('frontend/contact', [
            'email' => ConfigDictionary::get('email'),
            'phone' => ConfigDictionary::get('phone'),
            'address' => ConfigDictionary::get('address'),
        ]);
    }

    public function contactStore(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:30',
            'subject' => 'nullable|string|max:255',
            'message' => 'required|string',
        ]);

        Contact::create($validated);

        return back()->with('success', 'Your message has been sent.');
    }

    public function staticPage(string $page): Response
    {
        $keyMap = [
            'about' => 'about_us',
            'faq' => 'faq',
            'size-guide' => 'size_guide',
            'refund-policy' => 'refund_policy',
            'cancellation-policy' => 'cancel_policy',
            'privacy-policy' => 'privacy_policy',
            'terms-policy' => 'terms_of_service',
        ];

        return Inertia::render('frontend/static-page', [
            'page' => $page,
            'content' => ConfigDictionary::get($keyMap[$page] ?? $page, ''),
        ]);
    }

    private function applyFilters($query, Request $request): mixed
    {
        if ($request->filled('min_price')) {
            $query->where('sale_price', '>=', $request->min_price);
        }
        if ($request->filled('max_price')) {
            $query->where('sale_price', '<=', $request->max_price);
        }
        if ($request->filled('colors')) {
            foreach ((array) $request->colors as $color) {
                $query->whereJsonContains('colors', $color);
            }
        }
        if ($request->filled('sizes')) {
            foreach ((array) $request->sizes as $size) {
                $query->whereJsonContains('sizes', $size);
            }
        }

        match ($request->sort_by ?? 'newest') {
            'price_low' => $query->orderBy('sale_price', 'asc'),
            'price_high' => $query->orderBy('sale_price', 'desc'),
            'name' => $query->orderBy('name', 'asc'),
            default => $query->orderBy('id', 'desc'),
        };

        return $query;
    }

    private function formatProduct(Product $product, bool $withDetails = false): array
    {
        $data = [
            'id' => $product->id,
            'name' => $product->name,
            'slug' => $product->slug,
            'image' => $product->image,
            'sale_price' => (float) $product->sale_price,
            'discount_price' => (float) $product->discount_price,
            'price' => (float) ($product->discount_price > 0 ? $product->discount_price : $product->sale_price),
            'colors' => $product->colors ?? [],
            'sizes' => $product->sizes ?? [],
            'type' => $product->type,
            'tailor_option' => $product->tailor_option,
            'tailor_price' => (float) $product->tailor_price,
            'has_variations' => ($product->variations_count ?? $product->variations()->count()) > 0,
            'youtube_link' => $product->youtube_link,
        ];

        if ($withDetails) {
            $data['photos'] = $product->photos->pluck('image');
            $data['variations'] = $product->variations->map(fn ($v) => [
                'id' => $v->id,
                'sku' => $v->sku,
                'price' => (float) $v->price,
                'stock' => $v->stock,
                'variation_data' => $v->variation_data,
            ]);
            $data['description'] = $product->description;
            $data['delivery_info'] = $product->delivery_info;
            $data['youtube_link'] = $product->youtube_link;
            $data['tailormeasurement'] = $product->tailormeasurement ?? [];
            $data['tags'] = $product->tags ?? [];
            $data['category'] = $product->category?->name;
            $data['brand'] = $product->brand?->name;
        }

        return $data;
    }
}
