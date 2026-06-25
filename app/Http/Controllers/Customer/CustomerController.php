<?php

namespace App\Http\Controllers\Customer;

use App\Enums\CommonStatus;
use App\Enums\CustomerRegistrationType;
use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Services\InventoryAccountingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;
use Inertia\Inertia;
use Inertia\Response;

class CustomerController extends Controller
{
    public function __construct(private InventoryAccountingService $accounting) {}

    public function index(Request $request): Response
    {
        $this->authorize('party.customer.view');

        $customers = Customer::with('branch')
            ->ownBranch()
            ->when($request->search, fn ($q, $s) => $q->where(function ($q) use ($s) {
                $q->where('name', 'like', "%{$s}%")
                    ->orWhere('phone', 'like', "%{$s}%")
                    ->orWhere('email', 'like', "%{$s}%");
            }))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('admin/inventory/customer/index', [
            'customers' => $customers,
            'filters' => $request->only('search'),
            'statuses' => collect(CommonStatus::cases())->map(fn ($s) => ['value' => $s->value, 'name' => $s->name]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('party.customer.create');

        $branchId = auth()->user()?->branch_id;

        $data = $request->validate([
            'name' => ['nullable', 'string', 'max:255'],
            'phone' => [
                'required',
                'string',
                'min:11',
                'max:11',
                Rule::unique('customers', 'phone')->where(fn ($query) => $query->where('branch_id', $branchId)),
            ],
            'email' => ['nullable', 'string', 'email', 'max:255', 'unique:customers,email'],
            'address' => ['nullable', 'string', 'max:255'],
            'opening_balance' => ['nullable', 'numeric', 'min:0'],
            'is_default' => ['required', 'in:0,1'],
            'status' => ['required', new Enum(CommonStatus::class)],
        ]);

        $data['password'] = '12345678';

        $data['branch_id'] = $branchId;
        $data['registration_type'] = CustomerRegistrationType::Offline;

        if ((int) $data['is_default'] === 1) {
            Customer::where('branch_id', $data['branch_id'])->where('is_default', true)->update(['is_default' => false]);
        }

        $openingBalance = (float) ($data['opening_balance'] ?? 0);
        unset($data['opening_balance']);
        $data['balance'] = $openingBalance;

        $customer = Customer::create($data);

        if ($openingBalance > 0) {
            $this->accounting->postCustomerOpeningBalance(
                $customer,
                $openingBalance,
                now()->format('Y-m-d'),
            );
        }

        return back()->with('success', 'Customer created successfully.');
    }

    public function update(Request $request, Customer $customer): RedirectResponse
    {
        $this->authorize('party.customer.update');

        $data = $request->validate([
            'name' => ['nullable', 'string', 'max:255'],
            'phone' => [
                'required',
                'string',
                'min:11',
                'max:11',
                Rule::unique('customers', 'phone')
                    ->where(fn ($query) => $query->where('branch_id', $customer->branch_id))
                    ->ignore($customer->id),
            ],
            'email' => ['nullable', 'string', 'email', 'max:255', Rule::unique('customers', 'email')->ignore($customer->id)],
            'address' => ['nullable', 'string', 'max:255'],
            'is_default' => ['required', 'in:0,1'],
            'status' => ['required', new Enum(CommonStatus::class)],
        ]);

        if ((int) $data['is_default'] === 1 && ! $customer->is_default) {
            Customer::where('branch_id', $customer->branch_id)->where('is_default', true)->update(['is_default' => false]);
        }

        $customer->update($data);

        return back()->with('success', 'Customer updated successfully.');
    }

    public function destroy(Customer $customer): RedirectResponse
    {
        $this->authorize('party.customer.delete');

        if ($customer->is_default) {
            return back()->with('error', 'Cannot delete the default customer.');
        }

        if ($customer->sells()->exists()) {
            return back()->with('error', 'Cannot delete a customer with existing sales.');
        }

        $customer->delete();

        return back()->with('success', 'Customer deleted successfully.');
    }
}
