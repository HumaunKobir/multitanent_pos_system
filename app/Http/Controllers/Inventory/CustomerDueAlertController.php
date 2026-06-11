<?php

namespace App\Http\Controllers\Inventory;

use App\Enums\CustomerDueAlertStatus;
use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\CustomerDueAlert;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class CustomerDueAlertController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('party.customer-due-alert.view');

        $alerts = CustomerDueAlert::query()
            ->ownBranch()
            ->with(['customer:id,name,phone'])
            ->when($request->search, function ($query, string $search) {
                $query->whereHas('customer', fn ($q) => $q
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                );
            })
            ->latest()
            ->paginate(20)
            ->withQueryString();

        $customers = Customer::ownBranch()
            ->orderBy('name')
            ->get(['id', 'name', 'phone', 'balance']);

        return Inertia::render('admin/inventory/customer-due-alert/index', [
            'alerts' => $alerts,
            'customers' => $customers,
            'filters' => $request->only('search'),
            'today' => now()->format('Y-m-d'),
            'statuses' => collect(CustomerDueAlertStatus::cases())
                ->map(fn ($s) => ['value' => $s->value, 'name' => $s->name]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('party.customer-due-alert.create');

        $data = $request->validate([
            'customer_id' => ['required', 'exists:customers,id'],
            'due_given_date' => ['required', 'date'],
            'status' => ['required', new Enum(CustomerDueAlertStatus::class)],
        ]);

        $customer = Customer::ownBranch()->whereKey($data['customer_id'])->first();

        if ($customer === null) {
            throw ValidationException::withMessages([
                'customer_id' => 'Customer not found for your branch.',
            ]);
        }

        if ((float) $customer->balance <= 0) {
            throw ValidationException::withMessages([
                'customer_id' => 'This customer has no outstanding due.',
            ]);
        }

        $data['branch_id'] = Auth::user()?->branch_id;

        CustomerDueAlert::create($data);

        return back()->with('success', 'Customer due alert created successfully.');
    }

    public function update(Request $request, CustomerDueAlert $customerDueAlert): RedirectResponse
    {
        $this->authorize('party.customer-due-alert.update');
        $this->authorizeBranch($customerDueAlert);

        $data = $request->validate([
            'customer_id' => ['required', 'exists:customers,id'],
            'due_given_date' => ['required', 'date'],
            'status' => ['required', new Enum(CustomerDueAlertStatus::class)],
        ]);

        $customerDueAlert->update($data);

        return back()->with('success', 'Customer due alert updated successfully.');
    }

    public function destroy(CustomerDueAlert $customerDueAlert): RedirectResponse
    {
        $this->authorize('party.customer-due-alert.delete');
        $this->authorizeBranch($customerDueAlert);

        $customerDueAlert->delete();

        return back()->with('success', 'Customer due alert deleted successfully.');
    }

    private function authorizeBranch(CustomerDueAlert $customerDueAlert): void
    {
        $branchId = Auth::user()?->branch_id;

        if ($branchId !== null && $customerDueAlert->branch_id !== $branchId) {
            abort(404);
        }
    }
}
