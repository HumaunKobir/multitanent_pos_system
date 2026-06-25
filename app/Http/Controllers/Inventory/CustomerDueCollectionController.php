<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Concerns\ProvidesPaymentAccounts;
use App\Http\Controllers\Concerns\UsesInventoryAccounting;
use App\Http\Controllers\Controller;
use App\Models\ChartOfAccount;
use App\Models\Customer;
use App\Models\CustomerPayment;
use App\Models\Transaction;
use App\Services\InventoryAccountingService;
use App\Services\PartyPaymentAllocationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class CustomerDueCollectionController extends Controller
{
    use ProvidesPaymentAccounts;
    use UsesInventoryAccounting;

    public function __construct(
        private InventoryAccountingService $accounting,
        private PartyPaymentAllocationService $allocations,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('party.customer-due-collection.view');

        $payments = CustomerPayment::query()
            ->ownBranch()
            ->with([
                'customer:id,name,phone',
                'createdBy:id,name',
                'allocations.sell:id',
            ])
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

        $paymentAccounts = $this->paymentAccounts();
        $paymentAccountLabels = collect($paymentAccounts)->keyBy('id');

        $payments->through(function (CustomerPayment $payment) use ($paymentAccountLabels) {
            $paymentAccountId = $this->resolvePaymentAccountId($payment, 'debit_account_id');

            return [
                ...$payment->toArray(),
                'payment_account_id' => $paymentAccountId,
                'payment_account_label' => $paymentAccountId !== null
                    ? $paymentAccountLabels->get($paymentAccountId)['label'] ?? null
                    : null,
            ];
        });

        $customers = Customer::ownBranch()
            ->orderBy('name')
            ->get(['id', 'name', 'phone', 'balance']);

        return Inertia::render('admin/inventory/customer-due-collection/index', [
            'payments' => $payments,
            'customers' => $customers,
            'filters' => $request->only('search'),
            'today' => now()->format('Y-m-d'),
            'paymentAccounts' => $paymentAccounts,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('party.customer-due-collection.create');

        $data = $request->validate([
            'customer_id' => ['required', 'exists:customers,id'],
            'date' => ['required', 'date'],
            'payment_account_id' => ['required', 'integer', 'exists:chart_of_accounts,id'],
            'comment' => ['nullable', 'string', 'max:1000'],
            'allocations' => ['required', 'array', 'min:1'],
            'allocations.*.sell_id' => ['required', 'integer', 'exists:sells,id'],
            'allocations.*.amount' => ['required', 'numeric', 'min:0.01'],
        ]);

        $branchId = Auth::user()?->branch_id;
        $paymentAccountId = $this->requirePaymentAccountId($request);
        $customer = Customer::ownBranch()->whereKey($data['customer_id'])->first();

        if ($customer === null) {
            throw ValidationException::withMessages([
                'customer_id' => 'Customer not found for your branch.',
            ]);
        }

        $validated = $this->allocations->validateCustomerAllocations($customer, $data['allocations']);
        $amount = $validated['total'];

        try {
            DB::transaction(function () use ($data, $branchId, $customer, $amount, $paymentAccountId, $validated) {
                $payment = CustomerPayment::create([
                    'branch_id' => $branchId,
                    'customer_id' => $customer->id,
                    'date' => $data['date'],
                    'amount' => $amount,
                    'comment' => $data['comment'] ?? null,
                    'created_by' => Auth::id(),
                    'serial' => 'INVCP'.str_pad((string) (CustomerPayment::max('id') + 1), 8, '0', STR_PAD_LEFT),
                ]);

                $this->allocations->applyCustomerAllocations($payment, $data['allocations'], $validated['sells']);
                $customer->decrement('balance', $amount);
                $this->accounting->postCustomerPayment($payment->fresh(['customer']), $paymentAccountId);
            });
        } catch (\Throwable $e) {
            if ($this->isInsufficientBalanceException($e)) {
                return back()
                    ->with('warning', 'Insufficient balance in the selected payment account.')
                    ->withInput();
            }

            throw $e;
        }

        $payment = CustomerPayment::query()->latest('id')->first();

        return back()->with('success', "Due collection {$payment?->invoice_number} recorded successfully.");
    }

    public function destroy(CustomerPayment $customerPayment): RedirectResponse
    {
        $this->authorize('party.customer-due-collection.delete');
        $this->authorizeBranch($customerPayment);

        $customer = $customerPayment->customer;

        DB::transaction(function () use ($customerPayment, $customer) {
            $this->allocations->reverseCustomerAllocations($customerPayment);

            if ($customer !== null) {
                $customer->increment('balance', (float) $customerPayment->amount);
            }

            $this->accounting->reverseFor($customerPayment);
            $customerPayment->delete();
        });

        return back()->with('success', 'Due collection deleted successfully.');
    }

    public function update(Request $request, CustomerPayment $customerPayment): RedirectResponse
    {
        $this->authorize('party.customer-due-collection.update');
        $this->authorizeBranch($customerPayment);

        $data = $request->validate([
            'customer_id' => ['required', 'exists:customers,id'],
            'date' => ['required', 'date'],
            'payment_account_id' => ['required', 'integer', 'exists:chart_of_accounts,id'],
            'comment' => ['nullable', 'string', 'max:1000'],
            'allocations' => ['required', 'array', 'min:1'],
            'allocations.*.sell_id' => ['required', 'integer', 'exists:sells,id'],
            'allocations.*.amount' => ['required', 'numeric', 'min:0.01'],
        ]);

        $paymentAccountId = $this->requirePaymentAccountId($request);
        $customer = Customer::ownBranch()->whereKey($data['customer_id'])->first();

        if ($customer === null) {
            throw ValidationException::withMessages([
                'customer_id' => 'Customer not found for your branch.',
            ]);
        }

        $validated = $this->allocations->validateCustomerAllocations($customer, $data['allocations'], $customerPayment);
        $amount = $validated['total'];

        try {
            DB::transaction(function () use ($customerPayment, $customer, $data, $paymentAccountId, $validated, $amount) {
                $previousCustomer = Customer::ownBranch()->whereKey($customerPayment->customer_id)->lockForUpdate()->first();
                $nextCustomer = Customer::ownBranch()->whereKey($customer->id)->lockForUpdate()->first();

                $this->allocations->reverseCustomerAllocations($customerPayment);

                if ($previousCustomer !== null) {
                    $previousCustomer->increment('balance', (float) $customerPayment->amount);
                }

                $this->accounting->reverseFor($customerPayment);

                $customerPayment->update([
                    'customer_id' => $customer->id,
                    'date' => $data['date'],
                    'amount' => $amount,
                    'comment' => $data['comment'] ?? null,
                ]);

                $this->allocations->applyCustomerAllocations($customerPayment, $data['allocations'], $validated['sells']);

                if ($nextCustomer !== null) {
                    $nextCustomer->decrement('balance', $amount);
                }

                $this->accounting->postCustomerPayment($customerPayment->fresh(['customer']), $paymentAccountId);
            });
        } catch (\Throwable $e) {
            if ($this->isInsufficientBalanceException($e)) {
                return back()
                    ->with('warning', 'Insufficient balance in the selected payment account.')
                    ->withInput();
            }

            throw $e;
        }

        return back()->with('success', "Due collection {$customerPayment->fresh()->invoice_number} updated successfully.");
    }

    private function authorizeBranch(CustomerPayment $customerPayment): void
    {
        $branchId = Auth::user()?->branch_id;

        if ($branchId !== null && $customerPayment->branch_id !== $branchId) {
            abort(404);
        }
    }

    private function resolvePaymentAccountId(CustomerPayment $payment, string $column): ?int
    {
        $accountId = Transaction::query()
            ->where('source_type', CustomerPayment::class)
            ->where('source_id', $payment->id)
            ->latest('id')
            ->value($column);

        if ($accountId === null) {
            return null;
        }

        return ChartOfAccount::query()->paymentAccount()->whereKey($accountId)->exists()
            ? (int) $accountId
            : null;
    }
}
