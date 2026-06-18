<?php

namespace App\Http\Controllers\Setting;

use App\Concerns\ManagesBranchCatalog;
use App\Http\Controllers\Controller;
use App\Models\Warranty;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class WarrantyController extends Controller
{
    use ManagesBranchCatalog;

    public function index(Request $request): Response
    {
        $this->authorize('setting.warranty.view');

        $warranties = $this->branchCatalogQuery()
            ->when($request->search, fn ($q, $s) => $q->where('name', 'like', "%{$s}%"))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('admin/setting/warranty/index', [
            'warranties' => $warranties,
            'filters' => $request->only('search'),
        ]);
    }

    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $this->authorize('setting.warranty.create');

        $warranty = $this->storeCatalogRecords($request->validate([
            ...$this->catalogValidationRules(),
            'duration' => ['nullable', 'string', 'max:191'],
        ]));

        if ($request->wantsJson()) {
            return response()->json(['value' => (string) $warranty->id, 'label' => $warranty->name], 201);
        }

        return redirect()->route('setting.warranty.index')
            ->with('success', 'Warranty created successfully.');
    }

    public function update(Request $request, Warranty $warranty): RedirectResponse
    {
        $this->authorize('setting.warranty.update');
        $this->authorizeCatalogAccess($warranty);

        $warranty->update($request->validate([
            'name' => ['required', 'string', 'max:191'],
            'duration' => ['nullable', 'string', 'max:191'],
            'status' => ['required', 'in:0,1'],
        ]));

        return redirect()->route('setting.warranty.index')
            ->with('success', 'Warranty updated successfully.');
    }

    public function destroy(Warranty $warranty): RedirectResponse
    {
        $this->authorize('setting.warranty.delete');
        $this->authorizeCatalogAccess($warranty);

        $warranty->delete();

        return redirect()->route('setting.warranty.index')
            ->with('success', 'Warranty deleted successfully.');
    }

    protected function catalogModelClass(): string
    {
        return Warranty::class;
    }
}
