<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SendSubscriberMailRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'subject' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:10000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'subject.required' => 'Please enter an email subject.',
            'body.required' => 'Please enter an email message.',
        ];
    }
}
