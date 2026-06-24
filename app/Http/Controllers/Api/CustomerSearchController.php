<?php

namespace App\Http\Controllers\Api;

use App\Enums\CommonStatus;
use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Services\CoinService;
use App\Services\CustomerDueAlertService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomerSearchController extends Controller
{
    public function __construct(private CustomerDueAlertService $dueAlertService) {}

    public function store(Request $request): JsonResponse
    {
        $this->authorize('party.customer.create');

        $data = $request->validate([
            'name' => ['nullable', 'string', 'max:255'],
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
        $this->authorize('party.customer.view');

        $customers = Customer::ownBranch()
            ->when($request->search, fn ($q, $s) => $q->where(function ($q) use ($s) {
                $q->where('name', 'like', "%{$s}%")
                    ->orWhere('phone', 'like', "%{$s}%");
            }))
            ->limit(20)
            ->get(['id', 'name', 'phone']);

        return response()->json($customers);
    }

    public function dueAlert(Customer $customer): JsonResponse
    {
        $this->authorize('party.customer.view');

        $branchId = auth()->user()?->branch_id;

        if ($branchId !== null && $customer->branch_id !== $branchId) {
            abort(404);
        }

        $alert = $this->dueAlertService->findActiveAlert($customer->id, $branchId);

        if ($alert === null) {
            return response()->json(['active' => null]);
        }

        return response()->json([
            'active' => [
                'id' => $alert->id,
                'due_given_date' => $alert->due_given_date->format('Y-m-d'),
                'status' => $alert->status->value,
            ],
        ]);
    }

    public function coins(Customer $customer): JsonResponse
    {
        $this->authorize('party.customer.view');

        $branchId = auth()->user()?->branch_id;

        if ($branchId !== null && $customer->branch_id !== $branchId) {
            abort(404);
        }

        $coinService = app(CoinService::class);
        $settings = $coinService->settingsPayloadForBranch($branchId);

        return response()->json([
            'balance' => (float) $customer->point,
            'is_default' => (bool) $customer->is_default,
            'settings' => $settings,
        ]);
    }
}
