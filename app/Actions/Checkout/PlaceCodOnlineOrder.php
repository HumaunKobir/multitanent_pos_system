<?php

namespace App\Actions\Checkout;

use App\Models\Customer;
use App\Models\OnlineOrder;

class PlaceCodOnlineOrder
{
    public function __construct(private PlaceOnlineOrder $placeOnlineOrder) {}

    /**
     * @param  array<string, array<string, mixed>>  $cart
     * @param  array<string, mixed>  $checkoutData
     */
    public function execute(Customer $customer, array $cart, array $checkoutData): OnlineOrder
    {
        return $this->placeOnlineOrder->execute(
            $customer,
            $cart,
            $checkoutData,
            paymentMethod: 'cod',
        );
    }
}
