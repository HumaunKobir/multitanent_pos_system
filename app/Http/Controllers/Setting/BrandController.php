<?php

namespace App\Http\Controllers\Setting;

use App\Concerns\ManagesBranchCatalog;
use App\Concerns\StoresPublicImages;
use App\Http\Controllers\Controller;
use App\Models\Brand;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BrandController extends Controller
{
    use ManagesBranchCatalog, StoresPublicImages;

    public function index(Request $request): Response
    {
        $this->authorize('setting.brand.view');

        $brands = $this->branchCatalogQuery()
            ->when($request->search, fn ($q, $s) => $q->where('name', 'like', "%{$s}%"))
            ->latest()
            ->paginate(20)
            ->withQueryString()
            ->through(fn (Brand $brand): array => [
                'id' => $brand->id,
                'name' => $brand->name,
                'slug' => $brand->slug,
                'status' => $brand->status,
                'image' => $this->isStoredPublicImage($brand->image) ? $brand->image : null,
                'image_url' => $this->publicImageUrl($brand->image),
            ]);

        return Inertia::render('admin/setting/brand/index', [
            'brands' => $brands,
            'filters' => $request->only('search'),
        ]);
    }

    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $this->authorize('setting.brand.create');

        $data = $request->validate([
            ...$this->catalogValidationRules(),
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ]);

        if ($storedImage = $this->storePublicImage($request, 'image', 'brands')) {
            $data['image'] = $storedImage;
        }

        $brand = $this->storeCatalogRecords($data);

        if ($request->wantsJson()) {
            return response()->json(['value' => (string) $brand->id, 'label' => $brand->name], 201);
        }

        return redirect()->route('setting.brand.index')
            ->with('success', 'Brand created successfully.');
    }

    public function update(Request $request, Brand $brand): RedirectResponse
    {
        $this->authorize('setting.brand.update');
        $this->authorizeCatalogAccess($brand);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:191'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'status' => ['required', 'in:0,1'],
        ]);

        if ($storedImage = $this->storePublicImage($request, 'image', 'brands')) {
            $this->deletePublicImage($this->isStoredPublicImage($brand->image) ? $brand->image : null);
            $data['image'] = $storedImage;
        } else {
            unset($data['image']);
        }

        $brand->update($data);

        return redirect()->route('setting.brand.index')
            ->with('success', 'Brand updated successfully.');
    }

    public function destroy(Brand $brand): RedirectResponse
    {
        $this->authorize('setting.brand.delete');
        $this->authorizeCatalogAccess($brand);

        if ($brand->products()->exists()) {
            return back()->with('error', 'Cannot delete brand with existing products.');
        }

        $this->deletePublicImage($this->isStoredPublicImage($brand->image) ? $brand->image : null);

        $brand->delete();

        return redirect()->route('setting.brand.index')
            ->with('success', 'Brand deleted successfully.');
    }

    protected function catalogModelClass(): string
    {
        return Brand::class;
    }
}
