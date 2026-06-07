<?php

namespace App\Http\Controllers;

use App\Enums\OrderStatus;
use App\Models\OnlineOrder;
use App\Services\OnlineOrderAccountingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use RuntimeException;

class OnlineOrderController extends Controller
{
    public function __construct(private OnlineOrderAccountingService $accountingService) {}

    public function fulfill(Request $request, OnlineOrder $onlineOrder): RedirectResponse
    {
        $this->authorize('online-order.update');

        try {
            $this->accountingService->fulfill($onlineOrder);
        } catch (RuntimeException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', 'Order delivered and accounting posted successfully.');
    }

    public function updateStatus(Request $request, OnlineOrder $onlineOrder): RedirectResponse
    {
        $this->authorize('online-order.update');

        $data = $request->validate([
            'status' => ['required', 'integer'],
        ]);

        $status = OrderStatus::tryFrom((int) $data['status']);

        if ($status === null) {
            return back()->with('error', 'Invalid order status.');
        }

        if ($status === OrderStatus::Delivered) {
            return $this->fulfill($request, $onlineOrder);
        }

        $onlineOrder->update(['status' => $status]);

        return back()->with('success', 'Order status updated.');
    }
}
