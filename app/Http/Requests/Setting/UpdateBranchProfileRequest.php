<?php

namespace App\Http\Requests\Setting;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UpdateBranchProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('setting.branch-profile.update') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string', 'max:1000'],
            'logo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,gif,webp,svg', 'max:2048'],
            'email' => ['required', 'email', 'max:191', Rule::unique('users', 'email')->ignore($this->user()?->id)],
            'password' => ['nullable', 'confirmed', Password::min(8)],
        ];
    }
}
