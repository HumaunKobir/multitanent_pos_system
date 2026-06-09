<?php

namespace App\Services;

use App\Exceptions\SteadfastCourierException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class SteadfastCourierGateway
{
    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function createOrder(array $payload): array
    {
        return $this->send(
            fn (PendingRequest $client): Response => $client->post('/create_order', $payload),
            'Unable to create Steadfast consignment.',
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function getStatusByInvoice(string $invoice): array
    {
        return $this->send(
            fn (PendingRequest $client): Response => $client->get('/status_by_invoice/'.rawurlencode($invoice)),
            'Unable to fetch Steadfast delivery status.',
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function getStatusByTrackingCode(string $trackingCode): array
    {
        return $this->send(
            fn (PendingRequest $client): Response => $client->get('/status_by_trackingcode/'.rawurlencode($trackingCode)),
            'Unable to fetch Steadfast delivery status.',
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function getStatusByConsignmentId(int $consignmentId): array
    {
        return $this->send(
            fn (PendingRequest $client): Response => $client->get('/status_by_cid/'.$consignmentId),
            'Unable to fetch Steadfast delivery status.',
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function getBalance(): array
    {
        return $this->send(
            fn (PendingRequest $client): Response => $client->get('/get_balance'),
            'Unable to fetch Steadfast balance.',
        );
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
            }, throw: false);
    }

    /**
     * @param  callable(PendingRequest): Response  $request
     * @return array<string, mixed>
     */
    private function send(callable $request, string $fallbackMessage): array
    {
        try {
            $response = $request($this->client());
        } catch (ConnectionException $exception) {
            throw new SteadfastCourierException('Unable to connect to Steadfast. Please try again.', 0, $exception);
        }

        if ($response->failed()) {
            throw new SteadfastCourierException($this->extractErrorMessage($response, $fallbackMessage));
        }

        return $this->decodeResponse($response->json(), $fallbackMessage);
    }

    private function extractErrorMessage(Response $response, string $fallbackMessage): string
    {
        $body = $response->json();

        if (is_array($body) && filled($body['message'] ?? null)) {
            return (string) $body['message'];
        }

        $plainBody = trim($response->body());

        return filled($plainBody) ? $plainBody : $fallbackMessage;
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
                $this->formatApiError($body, $fallbackMessage),
            );
        }

        return $body;
    }

    /**
     * @param  array<string, mixed>  $body
     */
    private function formatApiError(array $body, string $fallbackMessage): string
    {
        if (filled($body['message'] ?? null)) {
            return (string) $body['message'];
        }

        $errors = $body['errors'] ?? null;

        if (! is_array($errors)) {
            return $fallbackMessage;
        }

        $messages = collect($errors)
            ->flatMap(function (mixed $fieldErrors): array {
                if (is_array($fieldErrors)) {
                    return array_map(strval(...), $fieldErrors);
                }

                return filled($fieldErrors) ? [(string) $fieldErrors] : [];
            })
            ->filter()
            ->values()
            ->all();

        return $messages !== [] ? implode(' ', $messages) : $fallbackMessage;
    }
}
