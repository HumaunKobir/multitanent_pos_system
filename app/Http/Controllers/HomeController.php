<?php

namespace App\Http\Controllers;

use App\Enums\BlockType;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Collectioncategory;
use App\Models\ConfigDictionary;
use App\Models\Contact;
use App\Models\Product;
use App\Models\ProductReview;
use App\Models\ProductSection;
use App\Models\Slider;
use App\Services\EcommerceBranchService;
use App\Support\StorageUrl;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class HomeController extends Controller
{
    public function __construct(private EcommerceBranchService $ecommerceBranch) {}

    public function index(): Response
    {
        $sliders = Slider::active()
            ->orderBy('id', 'desc')
            ->get()
            ->map(fn (Slider $slider): array => [
                'id' => $slider->id,
                'name' => $slider->name,
                'image' => StorageUrl::public($slider->image),
            ]);
        $collections = Collectioncategory::active()
            ->get()
            ->map(fn (Collectioncategory $collection): array => [
                'id' => $collection->id,
                'name' => $collection->name,
                'image' => StorageUrl::public($collection->image),
            ]);
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

        $reviews = $product->reviews()
            ->approved()
            ->latest()
            ->limit(20)
            ->get()
            ->map(fn (ProductReview $review): array => [
                'id' => $review->id,
                'reviewer_name' => $review->reviewer_name,
                'rating' => $review->rating,
                'comment' => $review->comment,
                'created_at' => $review->created_at?->diffForHumans(),
            ])
            ->values()
            ->all();

        $reviewCount = $product->reviews()->approved()->count();
        $averageRating = $reviewCount > 0
            ? round((float) $product->reviews()->approved()->avg('rating'), 1)
            : 0;

        return Inertia::render('frontend/single-product', [
            'product' => $this->formatProduct($product, true),
            'reviews' => $reviews,
            'reviewSummary' => [
                'average' => $averageRating,
                'count' => $reviewCount,
            ],
        ]);
    }

    public function sectionProducts(int $id, Request $request): Response
    {
        $section = ProductSection::findOrFail($id);
        $productIds = $section->items ?? [];

        $query = Product::with(['photos', 'variations'])->withCount('variations')
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
            'filters' => $request->only(['min_price', 'max_price', 'brands', 'sort_by']),
            'allBrands' => Brand::active()->pluck('name'),
        ]);
    }

    public function collectionProducts(string $name, Request $request): Response
    {
        $query = Product::with(['photos', 'variations'])->withCount('variations')
            ->where('status', 1)
            ->where('visible', 'yes')
            ->whereJsonContains('tags', $name);

        $query = $this->applyFilters($query, $request);

        $products = $query->paginate(12)->through(fn ($p) => $this->formatProduct($p));

        return Inertia::render('frontend/collection-products', [
            'collectionName' => $name,
            'products' => $products,
            'filters' => $request->only(['min_price', 'max_price', 'brands', 'sort_by']),
            'allBrands' => Brand::active()->pluck('name'),
        ]);
    }

    public function categoryProducts(string $category, Request $request): Response|RedirectResponse
    {
        if (ctype_digit($category)) {
            $model = Category::findOrFail((int) $category);

            return redirect()->route('category.products', $model->slug, 301);
        }

        $model = Category::where('slug', $category)->firstOrFail();

        return $this->renderCategoryProducts($model, $request);
    }

    public function brandProducts(string $brand, Request $request): Response|RedirectResponse
    {
        if (ctype_digit($brand)) {
            $model = Brand::findOrFail((int) $brand);

            return redirect()->route('brand.products', $model->slug, 301);
        }

        $model = Brand::where('slug', $brand)->firstOrFail();

        return $this->renderBrandProducts($model, $request);
    }

    private function renderCategoryProducts(Category $category, Request $request): Response
    {
        $query = Product::with(['photos', 'variations'])->withCount('variations')
            ->where('status', 1)
            ->where('visible', 'yes')
            ->where('category_id', $category->id);

        $query = $this->applyFilters($query, $request);

        $products = $query->paginate(12)->through(fn ($p) => $this->formatProduct($p));

        return Inertia::render('frontend/category-products', [
            'category' => [
                'id' => $category->id,
                'name' => $category->name,
                'slug' => $category->slug,
                'image' => StorageUrl::public($category->image),
            ],
            'products' => $products,
            'filters' => $request->only(['min_price', 'max_price', 'brands', 'sort_by']),
            'allBrands' => Brand::active()->pluck('name'),
        ]);
    }

    private function renderBrandProducts(Brand $brand, Request $request): Response
    {
        $query = Product::with(['photos', 'variations'])->withCount('variations')
            ->where('status', 1)
            ->where('visible', 'yes')
            ->where('brand_id', $brand->id);

        $query = $this->applyFilters($query, $request);

        $products = $query->paginate(12)->through(fn ($p) => $this->formatProduct($p));

        return Inertia::render('frontend/brand-products', [
            'brand' => $brand->only(['id', 'name', 'slug', 'image']),
            'products' => $products,
            'filters' => $request->only(['min_price', 'max_price', 'brands', 'sort_by']),
            'allBrands' => Brand::active()->pluck('name'),
        ]);
    }

    public function search(Request $request): Response
    {
        $query = $request->get('q', '');
        $products = Product::with(['photos', 'variations'])->withCount('variations')
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
        return Inertia::render('frontend/contact');
    }

    public function contactStore(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:30',
            'message' => 'required|string',
        ]);

        Contact::create([
            ...$validated,
            'branch_id' => $this->ecommerceBranch->resolveId(),
        ]);

        return back()->with('success', 'Your message has been sent.');
    }

    public function about(): Response
    {
        $heroImage = StorageUrl::public(ConfigDictionary::get('meta_banner'))
            ?? StorageUrl::public(ConfigDictionary::get('logo'));

        return Inertia::render('frontend/about', [
            'content' => ConfigDictionary::get('about_us', ''),
            'heroImage' => $heroImage,
        ]);
    }

    public function staticPage(string $page): Response
    {
        $keyMap = [
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
        $variationSummary = $this->variationSummary($product);

        $data = [
            'id' => $product->id,
            'name' => $product->name,
            'slug' => $product->slug,
            'image' => StorageUrl::public($product->image),
            'sale_price' => (float) $product->sale_price,
            'discount_price' => (float) $product->discount_price,
            'price' => $this->resolveDisplayPrice($product),
            'type' => $product->type,
            'tailor_option' => $product->tailor_option,
            'tailor_price' => (float) $product->tailor_price,
            'has_variations' => $variationSummary['variations_count'] > 0,
            'variations_count' => $variationSummary['variations_count'],
            'price_min' => $variationSummary['price_min'],
            'price_max' => $variationSummary['price_max'],
            'variations' => $variationSummary['variations'],
            'youtube_link' => $product->youtube_link,
        ];

        if ($withDetails) {
            $data['photos'] = $product->photos
                ->map(fn ($photo) => StorageUrl::public($photo->image))
                ->filter()
                ->values()
                ->all();
            $data['description'] = $product->description;
            $data['delivery_info'] = $product->delivery_info;
            $data['youtube_link'] = $product->youtube_link;
            $data['tailormeasurement'] = $product->tailormeasurement ?? [];
            $data['tags'] = $product->tags ?? [];
            $data['category'] = $product->category?->name;
            $data['category_slug'] = $product->category?->slug;
            $data['brand'] = $product->brand?->name;
        }

        return $data;
    }

    /**
     * @return array{
     *     variations: list<array{id: int, sku: ?string, price: float, stock: int, variation_data: array<string, string>}>,
     *     variations_count: int,
     *     price_min: ?float,
     *     price_max: ?float
     * }
     */
    private function variationSummary(Product $product): array
    {
        $variations = $product->relationLoaded('variations')
            ? $product->variations
            : collect();

        if ($variations->isEmpty()) {
            return [
                'variations' => [],
                'variations_count' => (int) ($product->variations_count ?? 0),
                'price_min' => null,
                'price_max' => null,
            ];
        }

        $formatted = $variations
            ->map(fn ($variation): array => [
                'id' => $variation->id,
                'sku' => $variation->sku,
                'price' => (float) $variation->price,
                'stock' => (int) $variation->stock,
                'variation_data' => $variation->variation_data ?? [],
            ])
            ->values()
            ->all();

        $prices = collect($formatted)
            ->pluck('price')
            ->filter(fn (float $price): bool => $price > 0)
            ->values();

        return [
            'variations' => $formatted,
            'variations_count' => count($formatted),
            'price_min' => $prices->isEmpty() ? null : (float) $prices->min(),
            'price_max' => $prices->isEmpty() ? null : (float) $prices->max(),
        ];
    }

    private function resolveDisplayPrice(Product $product): float
    {
        if ($product->discount_price > 0) {
            return (float) $product->discount_price;
        }

        if ($product->sale_price > 0) {
            return (float) $product->sale_price;
        }

        if ($product->relationLoaded('variations') && $product->variations->isNotEmpty()) {
            return (float) $product->variations->min('price');
        }

        $minVariationPrice = $product->variations()->min('price');

        return $minVariationPrice ? (float) $minVariationPrice : 0;
    }
}
