<?php

namespace App\Http\Controllers\Setting;

use App\Enums\BlockType;
use App\Enums\LayoutType;
use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductSection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ProductSectionController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('setting.productsection.view');

        $sections = ProductSection::query()
            ->forPanel()
            ->when($request->search, fn ($q, $s) => $q->where('name', 'like', "%{$s}%"))
            ->orderBy('serial')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (ProductSection $section): array => [
                'id' => $section->id,
                'name' => $section->name,
                'description' => $section->description,
                'button_text' => $section->button_text,
                'serial' => $section->serial,
                'layout_type' => $section->layout_type?->value,
                'layout_type_label' => $section->layout_type_label,
                'block_type' => $section->block_type?->value,
                'block_type_label' => $section->block_type_label,
                'block_per_line' => $section->block_per_line,
                'status' => $section->status,
                'items' => $section->items ?? [],
                'images' => $section->images ?? [],
            ]);

        return Inertia::render('admin/setting/product-section/index', [
            'sections' => $sections,
            'filters' => $request->only('search'),
            'layoutTypeOptions' => LayoutType::options(),
            'blockTypeOptions' => BlockType::options(),
            'productOptions' => $this->productOptions(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('setting.productsection.create');

        $data = $this->validatedData($request);

        $maxSerial = ProductSection::query()->forPanel()->max('serial') ?? 0;

        ProductSection::create([
            ...$data,
            'branch_id' => Auth::user()?->branch_id,
            'serial' => $maxSerial + 1,
        ]);

        return redirect()->route('setting.productsection.index')
            ->with('success', 'Product section created successfully.');
    }

    public function update(Request $request, ProductSection $productsection): RedirectResponse
    {
        $this->authorize('setting.productsection.update');

        $section = $this->resolveSection($productsection);
        $data = $this->validatedData($request, $section);

        if ($section->block_type === BlockType::Image && $data['block_type'] === BlockType::Item) {
            $this->deleteSectionImages($section);
        }

        $section->update($data);

        return redirect()->route('setting.productsection.index')
            ->with('success', 'Product section updated successfully.');
    }

    public function destroy(ProductSection $productsection): RedirectResponse
    {
        $section = $this->resolveSection($productsection);

        $this->deleteSectionImages($section);

        $section->delete();

        return redirect()->route('setting.productsection.index')
            ->with('success', 'Product section deleted successfully.');
    }

    public function updateOrder(Request $request): RedirectResponse
    {
        $this->authorize('setting.productsection.update');

        $validated = $request->validate([
            'orders' => ['required', 'array'],
            'orders.*.id' => ['required', 'integer', Rule::exists('product_sections', 'id')],
            'orders.*.serial' => ['required', 'integer'],
        ]);

        $panelIds = ProductSection::query()
            ->forPanel()
            ->pluck('id')
            ->all();

        foreach ($validated['orders'] as $order) {
            if (! in_array($order['id'], $panelIds, true)) {
                continue;
            }

            ProductSection::query()
                ->whereKey($order['id'])
                ->update(['serial' => $order['serial']]);
        }

        return redirect()->route('setting.productsection.index')
            ->with('success', 'Order updated successfully.');
    }

    /**
     * @return array<string, mixed>
     */
    protected function validatedData(Request $request, ?ProductSection $section = null): array
    {
        $blockType = BlockType::from((int) $request->input('block_type'));

        $layoutType = $section?->layout_type
            ?? LayoutType::tryFrom((int) $request->input('layout_type'))
            ?? LayoutType::Image_Block;

        $rules = [
            'block_type' => ['required', Rule::enum(BlockType::class)],
            'block_per_line' => ['required', 'integer', 'min:1', 'max:6'],
            'status' => ['required', 'in:0,1'],
            'name' => ['required', 'string', 'max:255'],
        ];

        if ($blockType === BlockType::Item && $layoutType !== LayoutType::Slider) {
            $rules['description'] = ['nullable', 'string'];
            $rules['button_text'] = ['nullable', 'string', 'max:255'];
        }

        if ($blockType === BlockType::Image) {
            $rules['image_name'] = ['nullable', 'array'];
            $rules['image_name.*'] = ['nullable', 'string', 'max:255'];
            $rules['images'] = [$section ? 'nullable' : 'required', 'array', 'min:1'];
            $rules['images.*'] = ['nullable', 'image', 'mimes:jpeg,png,jpg,gif,webp', 'max:2048'];
            $rules['button_text'] = ['nullable', 'array'];
            $rules['button_text.*'] = ['nullable', 'string', 'max:255'];
            $rules['link'] = ['nullable', 'array'];
            $rules['link.*'] = ['nullable', 'string', 'max:500'];
            $rules['description'] = ['nullable', 'array'];
            $rules['description.*'] = ['nullable', 'string'];
        }

        if ($blockType === BlockType::Item) {
            $rules['items'] = ['required', 'array', 'min:1'];
            $rules['items.*'] = ['integer', Rule::exists('products', 'id')];
        }

        if ($section === null) {
            $rules['layout_type'] = ['required', Rule::enum(LayoutType::class)];
        }

        $validated = $request->validate($rules);

        $layoutType = $section?->layout_type
            ?? LayoutType::from((int) $request->input('layout_type'));

        if ($blockType === BlockType::Image && $section === null) {
            $uploadedImages = collect($request->file('images', []))->filter();

            if ($uploadedImages->isEmpty()) {
                throw ValidationException::withMessages([
                    'images' => 'At least one image is required.',
                ]);
            }
        }

        $payload = [
            'name' => $validated['name'],
            'block_type' => $blockType,
            'block_per_line' => (int) $validated['block_per_line'],
            'status' => (int) $validated['status'],
            'description' => null,
            'button_text' => null,
            'images' => null,
            'items' => null,
        ];

        if ($blockType === BlockType::Item) {
            $payload['items'] = array_values(array_map('intval', $validated['items']));

            if ($layoutType !== LayoutType::Slider) {
                $payload['description'] = $validated['description'] ?? null;
                $payload['button_text'] = $validated['button_text'] ?? null;
            }
        }

        if ($blockType === BlockType::Image) {
            $payload['images'] = $this->buildImagesPayload($request, $section);
        }

        if ($section === null) {
            $payload['layout_type'] = $layoutType;
        }

        return $payload;
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function buildImagesPayload(Request $request, ?ProductSection $section = null): array
    {
        $existingImages = $section?->images ?? [];
        $imageNames = $request->input('image_name', []);
        $images = [];

        foreach ($imageNames as $key => $name) {
            $imagePath = $existingImages[$key]['image'] ?? null;

            if ($request->hasFile("images.{$key}")) {
                if (! empty($existingImages[$key]['image'])) {
                    Storage::disk('public')->delete($existingImages[$key]['image']);
                }

                $imagePath = $request->file("images.{$key}")->store('product-sections', 'public');
            }

            if ($imagePath === null) {
                continue;
            }

            $images[] = [
                'image_name' => $name,
                'image' => $imagePath,
                'button_text' => $request->input("button_text.{$key}"),
                'link' => $request->input("link.{$key}"),
                'description' => $request->input("description.{$key}"),
            ];
        }

        if ($images === [] && $section !== null) {
            return $existingImages;
        }

        return $images;
    }

    protected function deleteSectionImages(ProductSection $section): void
    {
        if ($section->block_type !== BlockType::Image || empty($section->images)) {
            return;
        }

        foreach ($section->images as $image) {
            if (! empty($image['image'])) {
                Storage::disk('public')->delete($image['image']);
            }
        }
    }

    protected function resolveSection(ProductSection $productsection): ProductSection
    {
        return ProductSection::query()->forPanel()->whereKey($productsection->id)->firstOrFail();
    }

    /**
     * @return list<array{value: int, label: string}>
     */
    protected function productOptions(): array
    {
        return Product::query()
            ->ownBranch()
            ->where('status', 1)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Product $product) => [
                'value' => $product->id,
                'label' => $product->name,
            ])
            ->values()
            ->all();
    }
}
