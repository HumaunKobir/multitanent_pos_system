<?php

namespace App\Http\Controllers\Customer;

use App\Enums\CommonStatus;
use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\MemberShipCard;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;
use Inertia\Inertia;
use Inertia\Response;

class CustomerController extends Controller
{
    public function index(Request $request): Response
    {
        $customers = Customer::with(['memberShipCard', 'branch'])
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
            'memberShipCards' => MemberShipCard::all(['id', 'name']),
            'statuses' => collect(CommonStatus::cases())->map(fn ($s) => ['value' => $s->value, 'name' => $s->name]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'member_ship_id' => ['nullable', 'integer', 'exists:member_ship_cards,id'],
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'min:11', 'max:11', 'unique:customers,phone'],
            'email' => ['nullable', 'string', 'email', 'max:255', 'unique:customers,email'],
            'password' => ['nullable', 'string', 'min:6'],
            'address' => ['nullable', 'string', 'max:255'],
            'is_default' => ['required', 'in:0,1'],
            'status' => ['required', new Enum(CommonStatus::class)],
        ]);

        if (empty($data['password'])) {
            $data['password'] = '12345678';
        }

        $data['branch_id'] = auth()->user()?->branch_id;

        if ((int) $data['is_default'] === 1) {
            Customer::where('branch_id', $data['branch_id'])->where('is_default', true)->update(['is_default' => false]);
        }

        Customer::create($data);

        return back()->with('success', 'Customer created successfully.');
    }

    public function update(Request $request, Customer $customer): RedirectResponse
    {
        $data = $request->validate([
            'member_ship_id' => ['nullable', 'integer', 'exists:member_ship_cards,id'],
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'min:11', 'max:11', Rule::unique('customers', 'phone')->ignore($customer->id)],
            'email' => ['nullable', 'string', 'email', 'max:255', Rule::unique('customers', 'email')->ignore($customer->id)],
            'password' => ['nullable', 'string', 'min:6'],
            'address' => ['nullable', 'string', 'max:255'],
            'is_default' => ['required', 'in:0,1'],
            'status' => ['required', new Enum(CommonStatus::class)],
        ]);

        if (empty($data['password'])) {
            unset($data['password']);
        }

        if ((int) $data['is_default'] === 1 && ! $customer->is_default) {
            Customer::where('branch_id', $customer->branch_id)->where('is_default', true)->update(['is_default' => false]);
        }

        $customer->update($data);

        return back()->with('success', 'Customer updated successfully.');
    }

    public function destroy(Customer $customer): RedirectResponse
    {
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
