<?php

namespace App\Services;

use App\Library\SslCommerz\SslCommerzNotification;

class SslCommerzGateway
{
    /**
     * @param  array<string, mixed>  $postData
     * @return array{url: ?string, error: ?string}
     */
    public function initiatePayment(array $postData): array
    {
        $sslc = new SslCommerzNotification;

        $result = $sslc->makePayment($postData, 'collect');

        if (is_string($result)) {
            return ['url' => null, 'error' => $result];
        }

        if (! is_array($result)) {
            return ['url' => null, 'error' => 'Unable to start payment.'];
        }

        if (! empty($result['GatewayPageURL'])) {
            return ['url' => $result['GatewayPageURL'], 'error' => null];
        }

        $message = $result['failedreason'] ?? 'Unable to start payment.';

        if (str_contains($message, 'Store Credential')) {
            $message = 'Check SSLCZ_TESTMODE and SSLCZ_STORE_PASSWORD in .env; use the sandbox API password, not the merchant panel password.';
        }

        return ['url' => null, 'error' => $message];
    }

    /**
     * @param  array<string, mixed>  $postData
     *
     * @deprecated Use initiatePayment() for Inertia-compatible redirects.
     */
    public function initiateHostedPayment(array $postData): ?string
    {
        $result = $this->initiatePayment($postData);

        return $result['error'];
    }

    /**
     * @param  array<string, mixed>  $postData
     */
    public function validateOrder(array $postData, string $transactionId, float $amount, string $currency = 'BDT'): bool
    {
        $sslc = new SslCommerzNotification;

        return $sslc->orderValidate($postData, $transactionId, $amount, $currency) === true;
    }
}
