<?php

namespace App\Actions\Checkout;

use App\Models\Customer;
use App\Models\OnlineOrder;
use App\Services\SslCommerzGateway;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response as HttpResponse;
use Inertia\Inertia;
use InvalidArgumentException;

class InitiateSslCommerzPayment
{
    public function __construct(
        private PlaceOnlineOrder $placeOnlineOrder,
        private SslCommerzGateway $sslCommerzGateway,
    ) {}

    /**
     * @param  array<string, array<string, mixed>>  $cart
     * @param  array<string, mixed>  $checkoutData
     */
    public function execute(Customer $customer, array $cart, array $checkoutData): RedirectResponse|HttpResponse
    {
        $order = $this->placeOnlineOrder->execute(
            $customer,
            $cart,
            $checkoutData,
            paymentMethod: 'sslcommerz',
        );

        $total = (float) $order->total;

        if ($total < 10) {
            throw new InvalidArgumentException('Online payment requires a minimum order total of ৳10.');
        }

        session()->forget('cart');

        $postData = $this->buildPaymentPayload($order, $checkoutData);

        $payment = $this->sslCommerzGateway->initiatePayment($postData);

        if ($payment['url'] === null) {
            $order->update(['payment_status' => 'Failed']);

            return redirect()->route('checkout')->with('error', $payment['error'] ?? 'Unable to start payment.');
        }

        return Inertia::location($payment['url']);
    }

    /**
     * @param  array<string, mixed>  $checkoutData
     * @return array<string, mixed>
     */
    private function buildPaymentPayload(OnlineOrder $order, array $checkoutData): array
    {
        $currency = config('sslcommerz.currency', 'BDT');
        $phone = $this->normalizePhone($checkoutData['phone']);

        return [
            'total_amount' => number_format((float) $order->total, 2, '.', ''),
            'currency' => $currency,
            'tran_id' => $order->transaction_id,
            'product_category' => 'Goods',
            'product_name' => 'Order #'.$order->id,
            'product_profile' => 'physical-goods',
            'cus_name' => $checkoutData['name'],
            'cus_email' => $checkoutData['email'] ?? 'customer@example.com',
            'cus_add1' => $checkoutData['address'],
            'cus_add2' => '',
            'cus_city' => '',
            'cus_state' => '',
            'cus_postcode' => '',
            'cus_country' => 'Bangladesh',
            'cus_phone' => $phone,
            'cus_fax' => '',
            'ship_name' => $checkoutData['name'],
            'ship_add1' => $checkoutData['address'],
            'ship_add2' => '',
            'ship_city' => '',
            'ship_state' => '',
            'ship_postcode' => '',
            'ship_phone' => $phone,
            'ship_country' => 'Bangladesh',
            'shipping_method' => 'NO',
            'value_a' => (string) $order->id,
        ];
    }

    private function normalizePhone(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';

        if (str_starts_with($digits, '880')) {
            return $digits;
        }

        if (str_starts_with($digits, '0')) {
            return '88'.$digits;
        }

        return '880'.$digits;
    }
}
