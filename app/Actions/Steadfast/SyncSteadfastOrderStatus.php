<?php

namespace App\Actions\Steadfast;

use App\Exceptions\SteadfastCourierException;
use App\Models\OnlineOrder;
use App\Services\SteadfastCourierGateway;

class SyncSteadfastOrderStatus
{
    public function __construct(
        private SteadfastCourierGateway $gateway,
        private ApplySteadfastDeliveryStatus $applySteadfastDeliveryStatus,
    ) {}

    public function execute(OnlineOrder $order): OnlineOrder
    {
        if (! $order->hasSteadfastShipment()) {
            throw new SteadfastCourierException('This order has not been sent to Steadfast.');
        }

        $invoice = $order->courier_invoice ?? $order->courierInvoice();
        $response = $this->gateway->getStatusByInvoice($invoice);
        $deliveryStatus = (string) ($response['delivery_status'] ?? '');

        if ($deliveryStatus === '') {
            throw new SteadfastCourierException('Steadfast did not return a delivery status.');
        }

        return $this->applySteadfastDeliveryStatus->execute($order, $deliveryStatus);
    }
}
