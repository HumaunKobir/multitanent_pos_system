<?php

namespace App\Http\Controllers;

use App\Models\OnlineOrder;
use App\Services\OnlineOrderAccountingService;
use App\Services\OnlineOrderNotificationService;
use App\Services\SslCommerzGateway;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class SslCommerzPaymentController extends Controller
{
    public function __construct(
        private SslCommerzGateway $sslCommerzGateway,
        private OnlineOrderAccountingService $onlineOrderAccounting,
        private OnlineOrderNotificationService $notifications,
    ) {}

    public function success(Request $request): RedirectResponse|Response
    {
        $transactionId = $request->input('tran_id');

        if (! is_string($transactionId) || $transactionId === '') {
            return redirect()->route('checkout')->with('error', 'Invalid payment response.');
        }

        $order = OnlineOrder::query()->where('transaction_id', $transactionId)->first();

        if ($order === null) {
            return redirect()->route('checkout')->with('error', 'Order not found for this payment.');
        }

        if ($order->payment_status === 'Paid') {
            return redirect()->route('checkout.success', ['order' => $order->id]);
        }

        if ($order->payment_status === 'Pending') {
            $validated = $this->sslCommerzGateway->validateOrder(
                $request->all(),
                $transactionId,
                (float) $order->total,
                config('sslcommerz.currency', 'BDT'),
            );

            if ($validated) {
                $this->markOrderPaid($order);

                return redirect()->route('checkout.success', ['order' => $order->id])
                    ->with('success', 'Payment completed successfully!');
            }
        }

        return redirect()->route('checkout')->with('error', 'Payment validation failed. Please contact support if amount was deducted.');
    }

    public function failure(Request $request): RedirectResponse
    {
        $this->markPendingOrderAs($request->input('tran_id'), 'Failed');

        return redirect()->route('checkout')->with('error', 'Payment failed. Please try again or choose Cash on Delivery.');
    }

    public function cancel(Request $request): RedirectResponse
    {
        $this->markPendingOrderAs($request->input('tran_id'), 'Cancelled');

        return redirect()->route('cart')->with('error', 'Payment was cancelled.');
    }

    public function ipn(Request $request): Response
    {
        $transactionId = $request->input('tran_id');

        if (! is_string($transactionId) || $transactionId === '') {
            return response('Invalid Data', 400);
        }

        $order = OnlineOrder::query()->where('transaction_id', $transactionId)->first();

        if ($order === null) {
            return response('Invalid Transaction', 404);
        }

        if ($order->payment_status === 'Paid') {
            return response('Transaction is already successfully Completed');
        }

        if ($order->payment_status === 'Pending') {
            $validated = $this->sslCommerzGateway->validateOrder(
                $request->all(),
                $transactionId,
                (float) $order->total,
                config('sslcommerz.currency', 'BDT'),
            );

            if ($validated) {
                $this->markOrderPaid($order);

                return response('Transaction is successfully Completed');
            }
        }

        return response('Invalid Transaction', 400);
    }

    private function markPendingOrderAs(mixed $transactionId, string $status): void
    {
        if (! is_string($transactionId) || $transactionId === '') {
            return;
        }

        OnlineOrder::query()
            ->where('transaction_id', $transactionId)
            ->where('payment_status', 'Pending')
            ->update(['payment_status' => $status]);
    }

    private function markOrderPaid(OnlineOrder $order): void
    {
        $order->update(['payment_status' => 'Paid']);
        $freshOrder = $order->fresh(['products']);
        $this->onlineOrderAccounting->recordPrepaymentIfNeeded($freshOrder);
        $this->notifications->sendPaymentConfirmedOnce($freshOrder);
    }
}
