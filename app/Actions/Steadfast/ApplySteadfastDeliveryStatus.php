<?php

namespace App\Actions\Steadfast;

use App\Enums\OrderStatus;
use App\Models\OnlineOrder;
use App\Services\OnlineOrderAccountingService;
use App\Services\OnlineOrderNotificationService;
use RuntimeException;

class ApplySteadfastDeliveryStatus
{
    public function __construct(
        private OnlineOrderAccountingService $accountingService,
        private OnlineOrderNotificationService $notifications,
    ) {}

    public function execute(OnlineOrder $order, string $deliveryStatus): OnlineOrder
    {
        $order->update([
            'courier_status' => $deliveryStatus,
        ]);

        if ($deliveryStatus === 'delivered') {
            return $this->markDelivered($order);
        }

        if ($deliveryStatus === 'cancelled') {
            return $this->markCancelled($order);
        }

        return $order->fresh(['products']);
    }

    private function markDelivered(OnlineOrder $order): OnlineOrder
    {
        if ($order->status === OrderStatus::Delivered) {
            return $order->fresh(['products']);
        }

        try {
            return $this->accountingService->fulfill($order);
        } catch (RuntimeException $exception) {
            report($exception);

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
        }
    }

    private function markCancelled(OnlineOrder $order): OnlineOrder
    {
        if (in_array($order->status, [OrderStatus::Delivered, OrderStatus::Canceled], true)) {
            return $order->fresh(['products']);
        }

        $order->update(['status' => OrderStatus::Canceled]);

        return $order->fresh(['products']);
    }
}
