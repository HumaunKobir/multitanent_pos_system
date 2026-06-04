<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Models\Supplier;
use App\Models\SupplierPayment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class SupplierPaymentController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('party.supplier-payment.view');

        $payments = SupplierPayment::query()
            ->ownBranch()
            ->with(['supplier:id,name,phone', 'createdBy:id,name'])
            ->when($request->search, function ($query, string $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('serial', 'like', "%{$search}%")
                        ->orWhere('comment', 'like', "%{$search}%")
                        ->orWhereHas('supplier', fn ($sq) => $sq
                            ->where('name', 'like', "%{$search}%")
                            ->orWhere('phone', 'like', "%{$search}%"));
                });
            })
            ->latest()
            ->paginate(20)
            ->withQueryString();

        $suppliers = Supplier::ownBranch()
            ->orderBy('name')
            ->get(['id', 'name', 'phone', 'balance']);

        return Inertia::render('admin/inventory/supplier-payment/index', [
            'payments' => $payments,
            'suppliers' => $suppliers,
            'filters' => $request->only('search'),
            'today' => now()->format('Y-m-d'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('party.supplier-payment.create');

        $data = $request->validate([
            'supplier_id' => ['required', 'exists:suppliers,id'],
            'date' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'comment' => ['nullable', 'string', 'max:1000'],
        ]);

        $branchId = Auth::user()?->branch_id;
        $supplier = Supplier::ownBranch()->whereKey($data['supplier_id'])->first();

        if ($supplier === null) {
            throw ValidationException::withMessages([
                'supplier_id' => 'Supplier not found for your branch.',
            ]);
        }

        $amount = (float) $data['amount'];

        if ($amount > (float) $supplier->balance) {
            throw ValidationException::withMessages([
                'amount' => 'Payment amount cannot exceed supplier due balance.',
            ]);
        }

        $payment = SupplierPayment::create([
            'branch_id' => $branchId,
            'supplier_id' => $supplier->id,
            'date' => $data['date'],
            'amount' => $amount,
            'comment' => $data['comment'] ?? null,
            'created_by' => Auth::id(),
            'serial' => 'INVSP'.str_pad((string) (SupplierPayment::max('id') + 1), 8, '0', STR_PAD_LEFT),
        ]);

        $supplier->decrement('balance', $amount);

        return back()->with('success', "Supplier payment {$payment->invoice_number} recorded successfully.");
    }

    public function destroy(SupplierPayment $supplierPayment): RedirectResponse
    {
        $this->authorize('party.supplier-payment.delete');
        $this->authorizeBranch($supplierPayment);

        $supplier = $supplierPayment->supplier;

        if ($supplier !== null) {
            $supplier->increment('balance', (float) $supplierPayment->amount);
        }

        $supplierPayment->delete();

        return back()->with('success', 'Supplier payment deleted successfully.');
    }

    private function authorizeBranch(SupplierPayment $supplierPayment): void
    {
        $branchId = Auth::user()?->branch_id;

        if ($branchId !== null && $supplierPayment->branch_id !== $branchId) {
            abort(404);
        }
    }
}
