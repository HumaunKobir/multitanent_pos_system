<?php

namespace App\Http\Controllers\Setting;

use App\Concerns\ManagesBranchCatalog;
use App\Http\Controllers\Controller;
use App\Models\Unit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class UnitController extends Controller
{
    use ManagesBranchCatalog;

    public function index(Request $request): Response
    {
        $this->authorize('setting.unit.view');

        $units = $this->branchCatalogQuery()
            ->when($request->search, fn ($q, $s) => $q->where('name', 'like', "%{$s}%"))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('admin/setting/unit/index', [
            'units' => $units,
            'filters' => $request->only('search'),
        ]);
    }

    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $this->authorize('setting.unit.create');

        $unit = $this->storeCatalogRecords($this->validatedCatalogData($request));

        if ($request->wantsJson()) {
            return response()->json(['value' => (string) $unit->id, 'label' => $unit->name], 201);
        }

        return redirect()->route('setting.unit.index')
            ->with('success', 'Unit created successfully.');
    }

    public function update(Request $request, Unit $unit): RedirectResponse
    {
        $this->authorize('setting.unit.update');
        $this->authorizeCatalogAccess($unit);

        $unit->update($request->validate([
            'name' => ['required', 'string', 'max:191'],
            'status' => ['required', 'in:0,1'],
        ]));

        return redirect()->route('setting.unit.index')
            ->with('success', 'Unit updated successfully.');
    }

    public function destroy(Unit $unit): RedirectResponse
    {
        $this->authorize('setting.unit.delete');
        $this->authorizeCatalogAccess($unit);

        $unit->delete();

        return redirect()->route('setting.unit.index')
            ->with('success', 'Unit deleted successfully.');
    }

    protected function catalogModelClass(): string
    {
        return Unit::class;
    }
}
