<?php

namespace App\Http\Controllers;

use App\Enums\CommonStatus;
use App\Models\Branch;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BranchController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('branch.view');

        $branches = Branch::query()
            ->when($request->search, fn ($q, $s) => $q->where('name', 'like', "%{$s}%"))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('admin/branch/index', [
            'branches' => $branches,
            'filters' => $request->only('search'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('branch.create');

        $data = $request->validate([
            'name' => ['required', 'string', 'max:191'],
            'phone' => ['required', 'string', 'max:20'],
            'address' => ['required', 'string'],
        ]);

        Branch::create([
            ...$data,
            'status' => CommonStatus::Active,
        ]);

        return redirect()->route('branch.index')
            ->with('success', 'Branch created successfully.');
    }

    public function update(Request $request, Branch $branch): RedirectResponse
    {
        $this->authorize('branch.update');

        $data = $request->validate([
            'name' => ['required', 'string', 'max:191'],
            'phone' => ['required', 'string', 'max:20'],
            'address' => ['required', 'string'],
            'status' => ['required', 'in:1,2'],
        ]);

        $branch->update([
            ...$data,
            'status' => (int) $data['status'],
        ]);

        return redirect()->route('branch.index')
            ->with('success', 'Branch updated successfully.');
    }

    public function destroy(Branch $branch): RedirectResponse
    {
        $this->authorize('branch.delete');

        return redirect()->route('branch.index')
            ->with('error', 'Branch delete is not allowed.');
    }
}
