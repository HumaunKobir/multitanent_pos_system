<?php

namespace App\Http\Controllers\Setting;

use App\Http\Controllers\Controller;
use App\Models\Size;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SizeController extends Controller
{
    public function index(Request $request): Response
    {
        $sizes = Size::query()
            ->when($request->search, fn ($q, $s) => $q->where('name', 'like', "%{$s}%"))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('admin/setting/size/index', [
            'sizes' => $sizes,
            'filters' => $request->only('search'),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/setting/size/form');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:191'],
            'status' => ['required', 'in:0,1'],
        ]);

        Size::create($data);

        return redirect()->route('setting.size.index')
            ->with('success', 'Size created successfully.');
    }

    public function edit(Size $size): Response
    {
        return Inertia::render('admin/setting/size/form', [
            'size' => $size,
        ]);
    }

    public function update(Request $request, Size $size): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:191'],
            'status' => ['required', 'in:0,1'],
        ]);

        $size->update($data);

        return redirect()->route('setting.size.index')
            ->with('success', 'Size updated successfully.');
    }

    public function destroy(Size $size): RedirectResponse
    {
        $size->delete();

        return redirect()->route('setting.size.index')
            ->with('success', 'Size deleted successfully.');
    }
}
