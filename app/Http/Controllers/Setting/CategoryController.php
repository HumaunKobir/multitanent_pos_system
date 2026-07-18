<?php

namespace App\Http\Controllers\Setting;

use App\Concerns\ExportsCatalogSettingList;
use App\Concerns\ManagesBranchCatalog;
use App\Concerns\StoresPublicImages;
use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CategoryController extends Controller
{
    use ExportsCatalogSettingList, ManagesBranchCatalog, StoresPublicImages;

    public function index(Request $request): Response
    {
        $this->authorize('setting.category.view');

        $categories = $this->catalogListQuery($request)
            ->paginate(20)
            ->withQueryString()
            ->through(fn (Category $category): array => [
                'id' => $category->id,
                'name' => $category->name,
                'slug' => $category->slug,
                'status' => $category->status,
                'image' => $this->isStoredPublicImage($category->image) ? $category->image : null,
                'image_url' => $this->publicImageUrl($category->image),
                'created_at' => $category->created_at?->toIso8601String(),
            ]);

        return Inertia::render('admin/setting/category/index', [
            'categories' => $categories,
            'filters' => $request->only('search', 'date_from', 'date_to'),
        ]);
    }

    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $this->authorize('setting.category.create');

        $data = $request->validate([
            ...$this->catalogValidationRules(),
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ]);

        if ($storedImage = $this->storePublicImage($request, 'image', 'categories')) {
            $data['image'] = $storedImage;
        }

        $category = $this->storeCatalogRecords($data);

        if ($request->wantsJson()) {
            return response()->json(['value' => (string) $category->id, 'label' => $category->name], 201);
        }

        return redirect()->route('setting.category.index')
            ->with('success', 'Category created successfully.');
    }

    public function update(Request $request, Category $category): RedirectResponse
    {
        $this->authorize('setting.category.update');
        $this->authorizeCatalogAccess($category);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:191'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'status' => ['required', 'in:0,1'],
        ]);

        if ($storedImage = $this->storePublicImage($request, 'image', 'categories')) {
            $this->deletePublicImage($this->isStoredPublicImage($category->image) ? $category->image : null);
            $data['image'] = $storedImage;
        } else {
            unset($data['image']);
        }

        $category->update($data);

        return redirect()->route('setting.category.index')
            ->with('success', 'Category updated successfully.');
    }

    public function destroy(Category $category): RedirectResponse
    {
        $this->authorize('setting.category.delete');
        $this->authorizeCatalogAccess($category);

        if ($category->products()->exists()) {
            return back()->with('error', 'Cannot delete category with existing products.');
        }

        $this->deletePublicImage($this->isStoredPublicImage($category->image) ? $category->image : null);

        $category->delete();

        return redirect()->route('setting.category.index')
            ->with('success', 'Category deleted successfully.');
    }

    protected function catalogModelClass(): string
    {
        return Category::class;
    }

    protected function catalogExportPermission(): string
    {
        return 'setting.category.view';
    }

    protected function catalogExportTitle(): string
    {
        return 'Categories';
    }
}
