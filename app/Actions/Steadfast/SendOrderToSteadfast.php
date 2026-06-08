<?php

namespace App\Actions\Steadfast;

use App\Enums\OrderStatus;
use App\Exceptions\SteadfastCourierException;
use App\Models\OnlineOrder;
use App\Services\SteadfastCourierGateway;
use App\Support\SteadfastPhone;
use Illuminate\Support\Facades\DB;

class SendOrderToSteadfast
{
    public function __construct(private SteadfastCourierGateway $gateway) {}

    public function execute(OnlineOrder $order): OnlineOrder
    {
        if (! $order->canSendToSteadfast()) {
            throw new SteadfastCourierException($order->steadfastSendBlockReason() ?? 'This order cannot be sent to Steadfast.');
        }

        $order->loadMissing('products');

        $payload = [
            'invoice' => $order->courierInvoice(),
            'recipient_name' => $order->name,
            'recipient_phone' => SteadfastPhone::normalize($order->phone),
            'recipient_address' => $order->address,
            'cod_amount' => $order->steadfastCodAmount(),
            'note' => $order->steadfastDeliveryNote(),
            'item_description' => $order->steadfastItemDescription(),
            'delivery_type' => 0,
        ];

        if (filled($order->email)) {
            $payload['recipient_email'] = $order->email;
        }

        $response = $this->gateway->createOrder($payload);
        $consignment = $response['consignment'] ?? null;

        if (! is_array($consignment)) {
            throw new SteadfastCourierException('Steadfast did not return consignment details.');
        }

        return DB::transaction(function () use ($order, $consignment): OnlineOrder {
            $order->update([
                'courier' => 'steadfast',
                'courier_invoice' => $order->courierInvoice(),
                'courier_consignment_id' => $consignment['consignment_id'] ?? null,
                'courier_tracking_code' => $consignment['tracking_code'] ?? null,
                'courier_status' => $consignment['status'] ?? 'in_review',
                'courier_sent_at' => now(),
                'status' => OrderStatus::Shipping,
            ]);

            return $order->fresh(['products']);
        });
    }
}
