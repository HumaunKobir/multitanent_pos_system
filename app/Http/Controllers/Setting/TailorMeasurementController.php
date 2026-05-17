<?php

namespace App\Http\Controllers\Setting;

use App\Http\Controllers\Controller;
use App\Models\Tailormeasurement;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TailorMeasurementController extends Controller
{
    public function index(Request $request): Response
    {
        $measurements = Tailormeasurement::query()
            ->when($request->search, fn ($q, $s) => $q->where('name', 'like', "%{$s}%"))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('admin/setting/tailormeasurement/index', [
            'measurements' => $measurements,
            'filters' => $request->only('search'),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/setting/tailormeasurement/form');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:191'],
            'status' => ['required', 'in:0,1'],
        ]);

        Tailormeasurement::create($data);

        return redirect()->route('setting.tailormeasurement.index')
            ->with('success', 'Tailor measurement created successfully.');
    }

    public function edit(Tailormeasurement $tailormeasurement): Response
    {
        return Inertia::render('admin/setting/tailormeasurement/form', [
            'measurement' => $tailormeasurement,
        ]);
    }

    public function update(Request $request, Tailormeasurement $tailormeasurement): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:191'],
            'status' => ['required', 'in:0,1'],
        ]);

        $tailormeasurement->update($data);

        return redirect()->route('setting.tailormeasurement.index')
            ->with('success', 'Tailor measurement updated successfully.');
    }

    public function destroy(Tailormeasurement $tailormeasurement): RedirectResponse
    {
        $tailormeasurement->delete();

        return redirect()->route('setting.tailormeasurement.index')
            ->with('success', 'Tailor measurement deleted successfully.');
    }
}
