<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Models\Supplier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SupplierController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('party.supplier.view');

        $suppliers = Supplier::ownBranch()
            ->when($request->search, fn ($q, $s) => $q->where(function ($q) use ($s) {
                $q->where('name', 'like', "%{$s}%")
                    ->orWhere('phone', 'like', "%{$s}%")
                    ->orWhere('company_name', 'like', "%{$s}%");
            }))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('admin/inventory/supplier/index', [
            'suppliers' => $suppliers,
            'filters' => $request->only('search'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('party.supplier.create');

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:30'],
            'company_name' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:500'],
            'opening_balance' => ['nullable', 'numeric', 'min:0'],
        ]);

        $data['branch_id'] = auth()->user()?->branch_id;
        $data['balance'] = $data['opening_balance'] ?? 0;
        unset($data['opening_balance']);

        Supplier::create($data);

        return back()->with('success', 'Supplier created successfully.');
    }

    public function update(Request $request, Supplier $supplier): RedirectResponse
    {
        $this->authorize('party.supplier.update');

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:30'],
            'company_name' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:500'],
        ]);

        $supplier->update($data);

        return back()->with('success', 'Supplier updated successfully.');
    }

    public function destroy(Supplier $supplier): RedirectResponse
    {
        $this->authorize('party.supplier.delete');

        if ($supplier->purchases()->exists()) {
            return back()->with('error', 'Cannot delete supplier with purchase history.');
        }

        $supplier->delete();

        return back()->with('success', 'Supplier deleted successfully.');
    }
}
