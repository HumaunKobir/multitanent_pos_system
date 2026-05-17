<?php

namespace App\Http\Controllers\Setting;

use App\Http\Controllers\Controller;
use App\Models\Color;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ColorController extends Controller
{
    public function index(Request $request): Response
    {
        $colors = Color::query()
            ->when($request->search, fn ($q, $s) => $q->where('name', 'like', "%{$s}%"))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('admin/setting/color/index', [
            'colors' => $colors,
            'filters' => $request->only('search'),
        ]);
    }


    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:191'],
            'code' => ['nullable', 'string', 'max:191'],
            'status' => ['required', 'in:0,1'],
        ]);

        Color::create($data);

        return redirect()->route('setting.color.index')
            ->with('success', 'Color created successfully.');
    }


    public function update(Request $request, Color $color): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:191'],
            'code' => ['nullable', 'string', 'max:191'],
            'status' => ['required', 'in:0,1'],
        ]);

        $color->update($data);

        return redirect()->route('setting.color.index')
            ->with('success', 'Color updated successfully.');
    }

    public function destroy(Color $color): RedirectResponse
    {
        $color->delete();

        return redirect()->route('setting.color.index')
            ->with('success', 'Color deleted successfully.');
    }
}
