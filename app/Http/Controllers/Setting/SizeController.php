<?php

namespace App\Http\Controllers\Setting;

use App\Concerns\ManagesBranchCatalog;
use App\Http\Controllers\Controller;
use App\Models\Size;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SizeController extends Controller
{
    use ManagesBranchCatalog;

    public function index(Request $request): Response
    {
        $this->authorize('setting.size.view');

        $sizes = $this->branchCatalogQuery()
            ->when($request->search, fn ($q, $s) => $q->where('name', 'like', "%{$s}%"))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('admin/setting/size/index', [
            'sizes' => $sizes,
            'filters' => $request->only('search'),
        ]);
    }

    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $this->authorize('setting.size.create');

        $size = $this->storeCatalogRecords($this->validatedCatalogData($request));

        if ($request->wantsJson()) {
            return response()->json(['value' => $size->name, 'label' => $size->name], 201);
        }

        return redirect()->route('setting.size.index')
            ->with('success', 'Size created successfully.');
    }

    public function update(Request $request, Size $size): RedirectResponse
    {
        $this->authorize('setting.size.update');
        $this->authorizeCatalogAccess($size);

        $size->update($request->validate([
            'name' => ['required', 'string', 'max:191'],
            'status' => ['required', 'in:0,1'],
        ]));

        return redirect()->route('setting.size.index')
            ->with('success', 'Size updated successfully.');
    }

    public function destroy(Size $size): RedirectResponse
    {
        $this->authorize('setting.size.delete');
        $this->authorizeCatalogAccess($size);

        $size->delete();

        return redirect()->route('setting.size.index')
            ->with('success', 'Size deleted successfully.');
    }

    protected function catalogModelClass(): string
    {
        return Size::class;
    }
}
