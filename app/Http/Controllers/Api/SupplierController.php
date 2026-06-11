<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Supplier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SupplierController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $this->authorize('party.supplier.create');

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:30'],
            'company_name' => ['required', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:500'],
            'opening_balance' => ['nullable', 'numeric', 'min:0'],
        ]);

        $data['branch_id'] = auth()->user()?->branch_id;
        $data['balance'] = $data['opening_balance'] ?? 0;
        unset($data['opening_balance']);

        $supplier = Supplier::create($data);

        return response()->json($supplier->only(['id', 'name', 'phone', 'balance']), 201);
    }

    public function index(Request $request): JsonResponse
    {
        $this->authorize('party.supplier.view');

        $suppliers = Supplier::ownBranch()
            ->when($request->search, fn ($q, $s) => $q->where(function ($q) use ($s) {
                $q->where('name', 'like', "%{$s}%")
                    ->orWhere('phone', 'like', "%{$s}%");
            }))
            ->orderBy('name')
            ->limit(20)
            ->get(['id', 'name', 'phone', 'balance']);

        return response()->json($suppliers);
    }
}
