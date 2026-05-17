<?php

namespace App\Http\Controllers\Setting;

use App\Http\Controllers\Controller;
use App\Models\Warranty;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class WarrantyController extends Controller
{
    public function index(Request $request): Response
    {
        $warranties = Warranty::query()
            ->when($request->search, fn ($q, $s) => $q->where('name', 'like', "%{$s}%"))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('admin/setting/warranty/index', [
            'warranties' => $warranties,
            'filters' => $request->only('search'),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/setting/warranty/form');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:191'],
            'duration' => ['nullable', 'string', 'max:191'],
            'status' => ['required', 'in:0,1'],
        ]);

        Warranty::create($data);

        return redirect()->route('setting.warranty.index')
            ->with('success', 'Warranty created successfully.');
    }

    public function edit(Warranty $warranty): Response
    {
        return Inertia::render('admin/setting/warranty/form', [
            'warranty' => $warranty,
        ]);
    }

    public function update(Request $request, Warranty $warranty): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:191'],
            'duration' => ['nullable', 'string', 'max:191'],
            'status' => ['required', 'in:0,1'],
        ]);

        $warranty->update($data);

        return redirect()->route('setting.warranty.index')
            ->with('success', 'Warranty updated successfully.');
    }

    public function destroy(Warranty $warranty): RedirectResponse
    {
        $warranty->delete();

        return redirect()->route('setting.warranty.index')
            ->with('success', 'Warranty deleted successfully.');
    }
}
