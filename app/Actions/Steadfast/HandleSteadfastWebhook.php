<?php

namespace App\Actions\Steadfast;

use App\Exceptions\SteadfastCourierException;
use App\Models\OnlineOrder;

class HandleSteadfastWebhook
{
    public function __construct(private ApplySteadfastDeliveryStatus $applySteadfastDeliveryStatus) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function execute(array $payload): OnlineOrder
    {
        $consignmentId = (int) $payload['consignment_id'];
        $invoice = (string) $payload['invoice'];
        $status = (string) $payload['status'];

        $order = OnlineOrder::query()
            ->where('courier', 'steadfast')
            ->where(function ($query) use ($consignmentId, $invoice): void {
                $query->where('courier_consignment_id', $consignmentId)
                    ->orWhere('courier_invoice', $invoice);
            })
            ->latest('id')
            ->first();

        if ($order === null) {
            throw new SteadfastCourierException("No Steadfast order found for consignment {$consignmentId}.");
        }

        return $this->applySteadfastDeliveryStatus->execute($order, $status);
    }
}
