<?php

namespace App\Http\Controllers\Setting;

use App\Concerns\ManagesBranchCatalog;
use App\Http\Controllers\Controller;
use App\Models\Color;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ColorController extends Controller
{
    use ManagesBranchCatalog;

    public function index(Request $request): Response
    {
        $this->authorize('setting.color.view');

        $colors = $this->branchCatalogQuery()
            ->when($request->search, fn ($q, $s) => $q->where('name', 'like', "%{$s}%"))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('admin/setting/color/index', [
            'colors' => $colors,
            'filters' => $request->only('search'),
        ]);
    }

    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $this->authorize('setting.color.create');

        $color = $this->storeCatalogRecords($this->validatedCatalogData($request));

        if ($request->wantsJson()) {
            return response()->json(['value' => $color->name, 'label' => $color->name], 201);
        }

        return redirect()->route('setting.color.index')
            ->with('success', 'Color created successfully.');
    }

    public function update(Request $request, Color $color): RedirectResponse
    {
        $this->authorize('setting.color.update');
        $this->authorizeCatalogAccess($color);

        $color->update($request->validate([
            'name' => ['required', 'string', 'max:191'],
            'status' => ['required', 'in:0,1'],
        ]));

        return redirect()->route('setting.color.index')
            ->with('success', 'Color updated successfully.');
    }

    public function destroy(Color $color): RedirectResponse
    {
        $this->authorize('setting.color.delete');
        $this->authorizeCatalogAccess($color);

        $color->delete();

        return redirect()->route('setting.color.index')
            ->with('success', 'Color deleted successfully.');
    }

    protected function catalogModelClass(): string
    {
        return Color::class;
    }
}
