<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\CustomerPayment;
use App\Services\PartyPaymentAllocationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomerDueSalesController extends Controller
{
    public function __construct(private PartyPaymentAllocationService $allocations) {}

    public function __invoke(Request $request, Customer $customer): JsonResponse
    {
        $this->authorize('party.customer-due-collection.view');

        $branchId = $request->user()?->branch_id;

        if ($branchId !== null && (int) $customer->branch_id !== (int) $branchId) {
            abort(404);
        }

        $editingPayment = null;
        $editingPaymentId = $request->integer('current_payment_id');

        if ($editingPaymentId > 0) {
            $editingPayment = CustomerPayment::query()
                ->ownBranch()
                ->whereKey($editingPaymentId)
                ->firstOrFail();
        }

        return response()->json([
            'sales' => $this->allocations->dueSalesForCustomer($customer, $editingPayment),
        ]);
    }
}
