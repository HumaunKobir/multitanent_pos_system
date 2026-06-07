<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Concerns\ProvidesPaymentAccounts;
use App\Http\Controllers\Concerns\UsesInventoryAccounting;
use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\CustomerPayment;
use App\Services\InventoryAccountingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class CustomerDueCollectionController extends Controller
{
    use ProvidesPaymentAccounts;
    use UsesInventoryAccounting;

    public function __construct(private InventoryAccountingService $accounting) {}

    public function index(Request $request): Response
    {
        $this->authorize('party.customer-due-collection.view');

        $payments = CustomerPayment::query()
            ->ownBranch()
            ->with(['customer:id,name,phone', 'createdBy:id,name'])
            ->when($request->search, function ($query, string $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('serial', 'like', "%{$search}%")
                        ->orWhere('comment', 'like', "%{$search}%")
                        ->orWhereHas('customer', fn ($cq) => $cq
                            ->where('name', 'like', "%{$search}%")
                            ->orWhere('phone', 'like', "%{$search}%"));
                });
            })
            ->latest()
            ->paginate(20)
            ->withQueryString();

        $customers = Customer::ownBranch()
            ->orderBy('name')
            ->get(['id', 'name', 'phone', 'balance']);

        return Inertia::render('admin/inventory/customer-due-collection/index', [
            'payments' => $payments,
            'customers' => $customers,
            'filters' => $request->only('search'),
            'today' => now()->format('Y-m-d'),
            'paymentAccounts' => $this->paymentAccounts(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('party.customer-due-collection.create');

        $data = $request->validate([
            'customer_id' => ['required', 'exists:customers,id'],
            'date' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'payment_account_id' => ['required', 'integer', 'exists:chart_of_accounts,id'],
            'comment' => ['nullable', 'string', 'max:1000'],
        ]);

        $branchId = Auth::user()?->branch_id;
        $paymentAccountId = $this->requirePaymentAccountId($request);
        $customer = Customer::ownBranch()->whereKey($data['customer_id'])->first();

        if ($customer === null) {
            throw ValidationException::withMessages([
                'customer_id' => 'Customer not found for your branch.',
            ]);
        }

        $amount = (float) $data['amount'];

        if ($amount > (float) $customer->balance) {
            throw ValidationException::withMessages([
                'amount' => 'Collection amount cannot exceed customer due balance.',
            ]);
        }

        $payment = CustomerPayment::create([
            'branch_id' => $branchId,
            'customer_id' => $customer->id,
            'date' => $data['date'],
            'amount' => $amount,
            'comment' => $data['comment'] ?? null,
            'created_by' => Auth::id(),
            'serial' => 'INVCP'.str_pad((string) (CustomerPayment::max('id') + 1), 8, '0', STR_PAD_LEFT),
        ]);

        $customer->decrement('balance', $amount);

        $this->accounting->postCustomerPayment($payment->fresh(['customer']), $paymentAccountId);

        return back()->with('success', "Due collection {$payment->invoice_number} recorded successfully.");
    }

    public function destroy(CustomerPayment $customerPayment): RedirectResponse
    {
        $this->authorize('party.customer-due-collection.delete');
        $this->authorizeBranch($customerPayment);

        $customer = $customerPayment->customer;

        if ($customer !== null) {
            $customer->increment('balance', (float) $customerPayment->amount);
        }

        $this->accounting->reverseFor($customerPayment);

        $customerPayment->delete();

        return back()->with('success', 'Due collection deleted successfully.');
    }

    private function authorizeBranch(CustomerPayment $customerPayment): void
    {
        $branchId = Auth::user()?->branch_id;

        if ($branchId !== null && $customerPayment->branch_id !== $branchId) {
            abort(404);
        }
    }
}
