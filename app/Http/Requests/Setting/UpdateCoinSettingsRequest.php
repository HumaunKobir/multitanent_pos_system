<?php

namespace App\Http\Requests\Setting;

use App\Enums\CoinExpiryUnit;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCoinSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        if ($user === null) {
            return false;
        }

        if ($this->isMethod('POST')) {
            return $user->can('setting.coin-settings.create');
        }

        return $user->can('setting.coin-settings.update');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'enabled' => ['required', 'boolean'],
            'earn_spend_amount' => ['required', 'numeric', 'min:0.01'],
            'earn_coins' => ['required', 'numeric', 'min:0.01'],
            'coin_value' => ['required', 'numeric', 'min:0.01'],
            'min_redeem_coins' => ['required', 'numeric', 'min:0'],
            'max_redeem_percent' => ['required', 'numeric', 'min:0', 'max:100'],
            'expiry_value' => ['nullable', 'integer', 'min:1', 'required_with:expiry_unit'],
            'expiry_unit' => ['nullable', 'required_with:expiry_value', Rule::enum(CoinExpiryUnit::class)],
        ];
    }

    protected function prepareForValidation(): void
    {
        $expiryValue = $this->input('expiry_value');
        $expiryUnit = $this->input('expiry_unit');

        if ($expiryValue === '' || $expiryValue === null) {
            $this->merge([
                'expiry_value' => null,
                'expiry_unit' => null,
            ]);

            return;
        }

        if ($expiryUnit === '' || $expiryUnit === null) {
            $this->merge([
                'expiry_unit' => null,
            ]);
        }
    }
}
