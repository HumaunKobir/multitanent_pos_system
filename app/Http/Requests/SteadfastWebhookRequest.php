<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SteadfastWebhookRequest extends FormRequest
{
    public function authorize(): bool
    {
        $configuredToken = config('steadfast.webhook_bearer_token');

        if (! filled($configuredToken)) {
            return false;
        }

        $authorization = $this->header('Authorization');

        return is_string($authorization)
            && hash_equals('Bearer '.$configuredToken, $authorization);
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'consignment_id' => ['required', 'integer'],
            'invoice' => ['required', 'string'],
            'status' => ['required', 'string'],
            'cod_amount' => ['nullable', 'numeric'],
            'updated_at' => ['nullable', 'string'],
        ];
    }
}
