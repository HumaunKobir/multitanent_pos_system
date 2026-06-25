<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Supplier;
use App\Models\SupplierPayment;
use App\Services\PartyPaymentAllocationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SupplierDuePurchasesController extends Controller
{
    public function __construct(private PartyPaymentAllocationService $allocations) {}

    public function __invoke(Request $request, Supplier $supplier): JsonResponse
    {
        $this->authorize('party.supplier-payment.view');

        $branchId = $request->user()?->branch_id;

        if ($branchId !== null && (int) $supplier->branch_id !== (int) $branchId) {
            abort(404);
        }

        $editingPayment = null;
        $editingPaymentId = $request->integer('current_payment_id');

        if ($editingPaymentId > 0) {
            $editingPayment = SupplierPayment::query()
                ->ownBranch()
                ->whereKey($editingPaymentId)
                ->firstOrFail();
        }

        return response()->json([
            'purchases' => $this->allocations->duePurchasesForSupplier($supplier, $editingPayment),
        ]);
    }
}
