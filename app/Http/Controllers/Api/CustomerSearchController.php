<?php

namespace App\Http\Controllers\Api;

use App\Enums\CommonStatus;
use App\Http\Controllers\Controller;
use App\Models\Customer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomerSearchController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'min:11', 'max:11', 'unique:customers,phone'],
            'email' => ['nullable', 'string', 'email', 'max:255', 'unique:customers,email'],
            'address' => ['nullable', 'string', 'max:255'],
        ]);

        $data['password'] = bcrypt('12345678');
        $data['is_default'] = false;
        $data['status'] = CommonStatus::Active;
        $data['branch_id'] = auth()->user()?->branch_id;

        $customer = Customer::create($data);

        return response()->json($customer->only(['id', 'name', 'phone']), 201);
    }

    public function index(Request $request): JsonResponse
    {
        $customers = Customer::ownBranch()
            ->when($request->search, fn ($q, $s) => $q->where(function ($q) use ($s) {
                $q->where('name', 'like', "%{$s}%")
                    ->orWhere('phone', 'like', "%{$s}%");
            }))
            ->limit(20)
            ->get(['id', 'name', 'phone']);

        return response()->json($customers);
    }
}
