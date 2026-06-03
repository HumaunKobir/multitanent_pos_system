<?php

namespace App\Http\Controllers\Setting;

use App\Http\Controllers\Controller;
use App\Models\Unit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class UnitController extends Controller
{
    public function index(Request $request): Response
    {
        $units = Unit::query()
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
        $data = $request->validate([
            'name' => ['required', 'string', 'max:191'],
            'status' => ['required', 'in:0,1'],
        ]);

        $unit = Unit::create($data);

        if ($request->wantsJson()) {
            return response()->json(['value' => (string) $unit->id, 'label' => $unit->name], 201);
        }

        return redirect()->route('setting.unit.index')
            ->with('success', 'Unit created successfully.');
    }

    public function update(Request $request, Unit $unit): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:191'],
            'status' => ['required', 'in:0,1'],
        ]);

        $unit->update($data);

        return redirect()->route('setting.unit.index')
            ->with('success', 'Unit updated successfully.');
    }

    public function destroy(Unit $unit): RedirectResponse
    {
        $unit->delete();

        return redirect()->route('setting.unit.index')
            ->with('success', 'Unit deleted successfully.');
    }
}
