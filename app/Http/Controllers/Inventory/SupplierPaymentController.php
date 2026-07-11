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

        $branchId = Auth::user()?->branch_id;

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

        $paymentAccounts = $this->paymentAccountsForBranch($branchId);
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
            ->get(['id', 'name', 'company_name', 'phone', 'balance', 'branch_id']);

        $dueTotals = $this->allocations->totalPayableDueForSuppliers($suppliers);
        $this->allocations->syncSupplierBalancesFromPayables($suppliers);

        $suppliers = $suppliers->map(fn (Supplier $supplier): array => [
            'id' => $supplier->id,
            'name' => $supplier->name,
            'company_name' => $supplier->company_name,
            'phone' => $supplier->phone,
            'balance' => $dueTotals[$supplier->id] ?? 0.0,
            'total_due' => $dueTotals[$supplier->id] ?? 0.0,
        ])->values();

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
            'amount' => ['nullable', 'numeric', 'min:0.01'],
            'allocations' => ['nullable', 'array'],
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

        $allocations = $data['allocations'] ?? [];
        $validated = $this->allocations->validateSupplierPayment(
            $supplier,
            $allocations,
            isset($data['amount']) ? (float) $data['amount'] : null,
        );
        $amount = $validated['total'];

        try {
            DB::transaction(function () use ($data, $branchId, $supplier, $amount, $paymentAccountId, $validated, $allocations) {
                $payment = SupplierPayment::create([
                    'branch_id' => $branchId,
                    'supplier_id' => $supplier->id,
                    'date' => $data['date'],
                    'amount' => $amount,
                    'comment' => $data['comment'] ?? null,
                    'created_by' => Auth::id(),
                    'serial' => 'INVSP'.str_pad((string) (SupplierPayment::max('id') + 1), 8, '0', STR_PAD_LEFT),
                ]);

                $this->accounting->postSupplierPayment($payment->fresh(['supplier']), $paymentAccountId);

                if ($allocations !== [] && $validated['purchases']->isNotEmpty()) {
                    $this->allocations->applySupplierAllocations($payment, $allocations, $validated['purchases'], $paymentAccountId);
                } else {
                    $this->allocations->applySupplierPaymentAcrossDues($payment, $supplier, $amount, $paymentAccountId);
                }

                $this->allocations->syncSupplierBalancesFromPayables([$supplier->fresh()]);
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
            $this->accounting->reverseFor($supplierPayment);
            $supplierPayment->delete();

            if ($supplier !== null) {
                $this->allocations->syncSupplierBalancesFromPayables([$supplier->fresh()]);
            }
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
            'amount' => ['nullable', 'numeric', 'min:0.01'],
            'allocations' => ['nullable', 'array'],
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

        $allocations = $data['allocations'] ?? [];
        $validated = $this->allocations->validateSupplierPayment(
            $supplier,
            $allocations,
            isset($data['amount']) ? (float) $data['amount'] : null,
            $supplierPayment,
        );
        $amount = $validated['total'];

        try {
            DB::transaction(function () use ($supplierPayment, $supplier, $data, $paymentAccountId, $validated, $allocations, $amount) {
                $previousSupplier = Supplier::ownBranch()->whereKey($supplierPayment->supplier_id)->lockForUpdate()->first();
                $nextSupplier = Supplier::ownBranch()->whereKey($supplier->id)->lockForUpdate()->first();

                $this->allocations->reverseSupplierAllocations($supplierPayment);

                $this->accounting->reverseFor($supplierPayment);

                $supplierPayment->update([
                    'supplier_id' => $supplier->id,
                    'date' => $data['date'],
                    'amount' => $amount,
                    'comment' => $data['comment'] ?? null,
                ]);

                $this->accounting->postSupplierPayment($supplierPayment->fresh(['supplier']), $paymentAccountId);

                if ($allocations !== [] && $validated['purchases']->isNotEmpty()) {
                    $this->allocations->applySupplierAllocations($supplierPayment, $allocations, $validated['purchases'], $paymentAccountId);
                } else {
                    $this->allocations->applySupplierPaymentAcrossDues($supplierPayment, $supplier, $amount, $paymentAccountId);
                }

                $suppliersToSync = collect([$previousSupplier, $nextSupplier])->filter()->unique('id')->values()->all();
                $this->allocations->syncSupplierBalancesFromPayables($suppliersToSync);
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
