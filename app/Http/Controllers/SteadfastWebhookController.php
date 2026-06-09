<?php

namespace App\Http\Controllers;

use App\Actions\Steadfast\HandleSteadfastWebhook;
use App\Exceptions\SteadfastCourierException;
use App\Http\Requests\SteadfastWebhookRequest;
use Illuminate\Http\JsonResponse;

class SteadfastWebhookController extends Controller
{
    public function __construct(private HandleSteadfastWebhook $handleSteadfastWebhook) {}

    public function __invoke(SteadfastWebhookRequest $request): JsonResponse
    {
        try {
            $this->handleSteadfastWebhook->execute($request->validated());
        } catch (SteadfastCourierException $exception) {
            return response()->json([
                'status' => 'error',
                'message' => $exception->getMessage(),
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Webhook received successfully.',
        ]);
    }
}
