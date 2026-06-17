<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Models\OnlineOrder;
use App\Models\Transaction;
use App\Support\WebsiteSettings;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class OnlineOrderAccountingService
{
    public function __construct(
        private InventoryAccountingService $accounting,
        private OnlineOrderStockService $stockService,
        private OnlineOrderNotificationService $notifications,
    ) {}

    public function recordPrepaymentIfNeeded(OnlineOrder $order): ?Transaction
    {
        if ($order->payment_method !== 'sslcommerz' || $order->payment_status !== 'Paid') {
            return null;
        }

        if ($this->accounting->findOnlineOrderTransaction($order, 'prepayment') !== null) {
            return null;
        }

        $paymentAccountId = WebsiteSettings::onlineSslCommerzPaymentAccountId();

        return $this->accounting->postOnlineOrderPrepayment($order, $paymentAccountId);
    }

    public function fulfill(OnlineOrder $order): OnlineOrder
    {
        if ($order->status === OrderStatus::Delivered) {
            throw new RuntimeException('This order has already been fulfilled.');
        }

        if ($order->payment_method === 'sslcommerz' && $order->payment_status !== 'Paid') {
            throw new RuntimeException('Online payment must be completed before fulfillment.');
        }

        if ($this->accounting->findOnlineOrderTransaction($order, 'fulfillment') !== null) {
            throw new RuntimeException('Accounting has already been posted for this order.');
        }

        return DB::transaction(function () use ($order): OnlineOrder {
            $branchId = EcommerceBranchService::resolveIdStatic();
            $cogs = $this->stockService->deductForOrder($order, $branchId);

            $paymentAccountId = $order->payment_method === 'cod'
                ? WebsiteSettings::onlineCodPaymentAccountId()
                : WebsiteSettings::onlineSslCommerzPaymentAccountId();

            $this->accounting->postOnlineOrderFulfillment($order, $paymentAccountId, $cogs);

            $updates = ['status' => OrderStatus::Delivered];

            if ($order->payment_method === 'cod') {
                $updates['payment_status'] = 'Paid';
            }

            $order->update($updates);

            $freshOrder = $order->fresh(['products']);

            if ($order->payment_method === 'cod') {
                $this->notifications->sendPaymentConfirmedOnce($freshOrder);
            }

            return $freshOrder;
        });
    }

    public function reversePrepaymentIfNeeded(OnlineOrder $order): ?Transaction
    {
        if ($order->payment_method !== 'sslcommerz' || $order->payment_status !== 'Paid') {
            return null;
        }

        if ($this->accounting->findOnlineOrderTransaction($order, 'prepayment') === null) {
            return null;
        }

        if ($this->accounting->findOnlineOrderTransaction($order, 'prepayment_reversal') !== null) {
            return null;
        }

        if ($this->accounting->findOnlineOrderTransaction($order, 'fulfillment') !== null) {
            return null;
        }

        $paymentAccountId = WebsiteSettings::onlineSslCommerzPaymentAccountId();

        return $this->accounting->reverseOnlineOrderPrepayment($order, $paymentAccountId);
    }
}
