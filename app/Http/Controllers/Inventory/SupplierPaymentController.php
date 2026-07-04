<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Concerns\ProvidesPaymentAccounts;
use App\Http\Controllers\Concerns\UsesInventoryAccounting;
use App\Http\Controllers\Controller;
use App\Models\Supplier;
use App\Models\SupplierPayment;
use App\Services\InventoryAccountingService;
use App\Services\PartyPaymentAllocationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class SupplierPaymentController extends Controller
{
    use ProvidesPaymentAccounts;
    use UsesInventoryAccounting;

    public function __construct(
        private InventoryAccountingService $accounting,
        private PartyPaymentAllocationService $allocations,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('party.supplier-payment.view');

        $payments = SupplierPayment::query()
            ->ownBranch()
            ->with([
                'supplier:id,name,company_name,phone',
                'createdBy:id,name',
                'allocations.purchase:id,gross_amount,discount,vat,paid_amount,due_amount',
            ])
            ->when($request->search, function ($query, string $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('serial', 'like', "%{$search}%")
                        ->orWhere('comment', 'like', "%{$search}%")
                        ->orWhereHas('supplier', fn ($sq) => $sq
                            ->where('name', 'like', "%{$search}%")
                            ->orWhere('company_name', 'like', "%{$search}%")
                            ->orWhere('phone', 'like', "%{$search}%"))
                        ->orWhereHas('allocations.purchase', fn ($pq) => $pq
                            ->where('serial', 'like', "%{$search}%"));
                });
            })
            ->latest()
            ->paginate(20)
            ->withQueryString();

        $paymentAccounts = $this->paymentAccounts();
        $paymentAccountLabels = collect($paymentAccounts)->keyBy('id');

        $payments->through(function (SupplierPayment $payment) use ($paymentAccountLabels) {
            $paymentAccountId = $this->accounting->paymentAccountIdFor($payment, latest: true);

            return [
                ...$payment->toArray(),
                'payment_account_id' => $paymentAccountId,
                'payment_account_label' => $paymentAccountId !== null
                    ? $paymentAccountLabels->get($paymentAccountId)['label'] ?? null
                    : null,
                'allocations' => $this->allocations->mapSupplierPaymentAllocationsForView($payment->allocations),
            ];
        });

        $suppliers = Supplier::ownBranch()
            ->orderBy('name')
            ->get(['id', 'name', 'company_name', 'phone', 'balance']);

        return Inertia::render('admin/inventory/supplier-payment/index', [
            'payments' => $payments,
            'suppliers' => $suppliers,
            'filters' => $request->only('search'),
            'today' => now()->format('Y-m-d'),
            'paymentAccounts' => $paymentAccounts,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('party.supplier-payment.create');

        $data = $request->validate([
            'supplier_id' => ['required', 'exists:suppliers,id'],
            'date' => ['required', 'date'],
            'payment_account_id' => ['required', 'integer', 'exists:chart_of_accounts,id'],
            'comment' => ['nullable', 'string', 'max:1000'],
            'allocations' => ['required', 'array', 'min:1'],
            'allocations.*.purchase_id' => ['required', 'integer', 'exists:purchases,id'],
            'allocations.*.amount' => ['required', 'numeric', 'min:0.01'],
        ]);

        $branchId = Auth::user()?->branch_id;
        $paymentAccountId = $this->requirePaymentAccountId($request);
        $supplier = Supplier::ownBranch()->whereKey($data['supplier_id'])->first();

        if ($supplier === null) {
            throw ValidationException::withMessages([
                'supplier_id' => 'Supplier not found for your branch.',
            ]);
        }

        $validated = $this->allocations->validateSupplierAllocations($supplier, $data['allocations']);
        $amount = $validated['total'];

        try {
            DB::transaction(function () use ($data, $branchId, $supplier, $amount, $paymentAccountId, $validated) {
                $payment = SupplierPayment::create([
                    'branch_id' => $branchId,
                    'supplier_id' => $supplier->id,
                    'date' => $data['date'],
                    'amount' => $amount,
                    'comment' => $data['comment'] ?? null,
                    'created_by' => Auth::id(),
                    'serial' => 'INVSP'.str_pad((string) (SupplierPayment::max('id') + 1), 8, '0', STR_PAD_LEFT),
                ]);

                $this->allocations->applySupplierAllocations($payment, $data['allocations'], $validated['purchases']);
                $supplier->decrement('balance', $amount);
                $this->accounting->postSupplierPayment($payment->fresh(['supplier']), $paymentAccountId);
            });
        } catch (\Throwable $e) {
            if ($this->isInsufficientBalanceException($e)) {
                return back()
                    ->with('warning', 'Insufficient balance in the selected payment account.')
                    ->withInput();
            }

            throw $e;
        }

        $payment = SupplierPayment::query()->latest('id')->first();

        return back()->with('success', "Supplier payment {$payment?->invoice_number} recorded successfully.");
    }

    public function destroy(SupplierPayment $supplierPayment): RedirectResponse
    {
        $this->authorize('party.supplier-payment.delete');
        $this->authorizeBranch($supplierPayment);

        $supplier = $supplierPayment->supplier;

        DB::transaction(function () use ($supplierPayment, $supplier) {
            $this->allocations->reverseSupplierAllocations($supplierPayment);

            if ($supplier !== null) {
                $supplier->increment('balance', (float) $supplierPayment->amount);
            }

            $this->accounting->reverseFor($supplierPayment);
            $supplierPayment->delete();
        });

        return back()->with('success', 'Supplier payment deleted successfully.');
    }

    public function update(Request $request, SupplierPayment $supplierPayment): RedirectResponse
    {
        $this->authorize('party.supplier-payment.update');
        $this->authorizeBranch($supplierPayment);

        $data = $request->validate([
            'supplier_id' => ['required', 'exists:suppliers,id'],
            'date' => ['required', 'date'],
            'payment_account_id' => ['required', 'integer', 'exists:chart_of_accounts,id'],
            'comment' => ['nullable', 'string', 'max:1000'],
            'allocations' => ['required', 'array', 'min:1'],
            'allocations.*.purchase_id' => ['required', 'integer', 'exists:purchases,id'],
            'allocations.*.amount' => ['required', 'numeric', 'min:0.01'],
        ]);

        $paymentAccountId = $this->requirePaymentAccountId($request);
        $supplier = Supplier::ownBranch()->whereKey($data['supplier_id'])->first();

        if ($supplier === null) {
            throw ValidationException::withMessages([
                'supplier_id' => 'Supplier not found for your branch.',
            ]);
        }

        $validated = $this->allocations->validateSupplierAllocations($supplier, $data['allocations'], $supplierPayment);
        $amount = $validated['total'];

        try {
            DB::transaction(function () use ($supplierPayment, $supplier, $data, $paymentAccountId, $validated, $amount) {
                $previousSupplier = Supplier::ownBranch()->whereKey($supplierPayment->supplier_id)->lockForUpdate()->first();
                $nextSupplier = Supplier::ownBranch()->whereKey($supplier->id)->lockForUpdate()->first();

                $this->allocations->reverseSupplierAllocations($supplierPayment);

                if ($previousSupplier !== null) {
                    $previousSupplier->increment('balance', (float) $supplierPayment->amount);
                }

                $this->accounting->reverseFor($supplierPayment);

                $supplierPayment->update([
                    'supplier_id' => $supplier->id,
                    'date' => $data['date'],
                    'amount' => $amount,
                    'comment' => $data['comment'] ?? null,
                ]);

                $this->allocations->applySupplierAllocations($supplierPayment, $data['allocations'], $validated['purchases']);

                if ($nextSupplier !== null) {
                    $nextSupplier->decrement('balance', $amount);
                }

                $this->accounting->postSupplierPayment($supplierPayment->fresh(['supplier']), $paymentAccountId);
            });
        } catch (\Throwable $e) {
            if ($this->isInsufficientBalanceException($e)) {
                return back()
                    ->with('warning', 'Insufficient balance in the selected payment account.')
                    ->withInput();
            }

            throw $e;
        }

        return back()->with('success', "Supplier payment {$supplierPayment->fresh()->invoice_number} updated successfully.");
    }

    private function authorizeBranch(SupplierPayment $supplierPayment): void
    {
        $branchId = Auth::user()?->branch_id;

        if ($branchId !== null && $supplierPayment->branch_id !== $branchId) {
            abort(404);
        }
    }
}
