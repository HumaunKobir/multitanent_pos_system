<?php

namespace App\Http\Requests\Setting;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePosTermsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('setting.pos-terms.update') ?? false;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'content' => ['nullable', 'string'],
        ];
    }
}
