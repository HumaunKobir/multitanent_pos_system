<?php

namespace App\Http\Requests\Setting;

use Illuminate\Foundation\Http\FormRequest;

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
        ];
    }
}
