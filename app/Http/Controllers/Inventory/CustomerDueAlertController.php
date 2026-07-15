<?php

namespace App\Http\Controllers\Inventory;

use App\Enums\CustomerDueAlertStatus;
use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\CustomerDueAlert;
use App\Models\Sell;
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
            ->with([
                'customer:id,name,phone',
                'sell:id,invoice_sequence,branch_id',
            ])
            ->when($request->search, function ($query, string $search) {
                $query->where(function ($query) use ($search) {
                    $query->whereHas('customer', fn ($q) => $q
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%")
                    );

                    $sequence = Sell::extractInvoiceSequence($search);

                    if ($sequence !== null && (
                        str_starts_with(strtoupper(trim($search)), Sell::invoicePrefix())
                        || (ctype_digit(trim($search)) && strlen(trim($search)) <= 8)
                    )) {
                        $query->orWhereHas('sell', fn ($q) => $q->where('invoice_sequence', $sequence));
                    }
                });
            })
            ->latest()
            ->paginate(20)
            ->withQueryString()
            ->through(fn (CustomerDueAlert $alert) => [
                ...$alert->toArray(),
                'invoice_number' => $alert->sell?->invoice_number,
            ]);

        $customers = Customer::ownBranch()
            ->orderBy('name')
            ->get(['id', 'name', 'phone', 'balance']);

        $customerIds = $customers->pluck('id');
        $linkedSellIds = CustomerDueAlert::query()
            ->ownBranch()
            ->whereNotNull('sell_id')
            ->pluck('sell_id');

        $dueSales = Sell::query()
            ->ownBranch()
            ->sale()
            ->where(function ($query) use ($customerIds, $linkedSellIds) {
                $query->whereIn('customer_id', $customerIds);

                if ($linkedSellIds->isNotEmpty()) {
                    $query->orWhereIn('id', $linkedSellIds);
                }
            })
            ->withSum('products as line_discount_total', 'discount')
            ->latest('id')
            ->get()
            ->filter(function (Sell $sell) use ($linkedSellIds) {
                if ($linkedSellIds->contains($sell->id)) {
                    return true;
                }

                return round((float) $sell->net_amount - (float) $sell->paid_amount, 2) > 0;
            })
            ->map(fn (Sell $sell) => [
                'id' => $sell->id,
                'customer_id' => $sell->customer_id,
                'invoice_number' => $sell->invoice_number,
                'due_amount' => round(max(0, (float) $sell->net_amount - (float) $sell->paid_amount), 2),
            ])
            ->values();

        return Inertia::render('admin/inventory/customer-due-alert/index', [
            'alerts' => $alerts,
            'customers' => $customers,
            'dueSales' => $dueSales,
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
            'sell_id' => ['nullable', 'exists:sells,id'],
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

        $data['sell_id'] = $this->validatedSellIdForCustomer(
            $data['sell_id'] ?? null,
            (int) $customer->id,
        );
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
            'sell_id' => ['nullable', 'exists:sells,id'],
            'due_given_date' => ['required', 'date'],
            'status' => ['required', new Enum(CustomerDueAlertStatus::class)],
        ]);

        $data['sell_id'] = $this->validatedSellIdForCustomer(
            $data['sell_id'] ?? null,
            (int) $data['customer_id'],
        );

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

    private function validatedSellIdForCustomer(mixed $sellId, int $customerId): ?int
    {
        if ($sellId === null || $sellId === '') {
            return null;
        }

        $sell = Sell::query()
            ->ownBranch()
            ->sale()
            ->whereKey($sellId)
            ->where('customer_id', $customerId)
            ->first();

        if ($sell === null) {
            throw ValidationException::withMessages([
                'sell_id' => 'Sale invoice not found for this customer.',
            ]);
        }

        return (int) $sell->id;
    }

    private function authorizeBranch(CustomerDueAlert $customerDueAlert): void
    {
        $branchId = Auth::user()?->branch_id;

        if ($branchId !== null && $customerDueAlert->branch_id !== $branchId) {
            abort(404);
        }
    }
}
