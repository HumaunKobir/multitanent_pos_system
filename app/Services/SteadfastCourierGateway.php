<?php

namespace App\Services;

use App\Exceptions\SteadfastCourierException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

class SteadfastCourierGateway
{
    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function createOrder(array $payload): array
    {
        $response = $this->client()->post('/create_order', $payload);

        return $this->decodeResponse($response->json(), 'Unable to create Steadfast consignment.');
    }

    /**
     * @return array<string, mixed>
     */
    public function getStatusByInvoice(string $invoice): array
    {
        $response = $this->client()->get('/status_by_invoice/'.rawurlencode($invoice));

        return $this->decodeResponse($response->json(), 'Unable to fetch Steadfast delivery status.');
    }

    /**
     * @return array<string, mixed>
     */
    public function getStatusByTrackingCode(string $trackingCode): array
    {
        $response = $this->client()->get('/status_by_trackingcode/'.rawurlencode($trackingCode));

        return $this->decodeResponse($response->json(), 'Unable to fetch Steadfast delivery status.');
    }

    /**
     * @return array<string, mixed>
     */
    public function getStatusByConsignmentId(int $consignmentId): array
    {
        $response = $this->client()->get('/status_by_cid/'.$consignmentId);

        return $this->decodeResponse($response->json(), 'Unable to fetch Steadfast delivery status.');
    }

    /**
     * @return array<string, mixed>
     */
    public function getBalance(): array
    {
        $response = $this->client()->get('/get_balance');

        return $this->decodeResponse($response->json(), 'Unable to fetch Steadfast balance.');
    }

    public function isConfigured(): bool
    {
        return filled(config('steadfast.api_key')) && filled(config('steadfast.secret_key'));
    }

    private function client(): PendingRequest
    {
        if (! $this->isConfigured()) {
            throw new SteadfastCourierException('Steadfast API credentials are not configured.');
        }

        return Http::baseUrl(rtrim((string) config('steadfast.base_url'), '/'))
            ->withHeaders([
                'Api-Key' => config('steadfast.api_key'),
                'Secret-Key' => config('steadfast.secret_key'),
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ])
            ->timeout((int) config('steadfast.timeout'))
            ->connectTimeout((int) config('steadfast.connect_timeout'))
            ->retry(2, 250, function (\Exception $exception): bool {
                return $exception instanceof ConnectionException;
            })
            ->throw();
    }

    /**
     * @param  array<string, mixed>|null  $body
     * @return array<string, mixed>
     */
    private function decodeResponse(?array $body, string $fallbackMessage): array
    {
        if (! is_array($body)) {
            throw new SteadfastCourierException($fallbackMessage);
        }

        $status = (int) ($body['status'] ?? 0);

        if ($status !== 200) {
            throw new SteadfastCourierException(
                (string) ($body['message'] ?? $fallbackMessage),
            );
        }

        return $body;
    }
}
