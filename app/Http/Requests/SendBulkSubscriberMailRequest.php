<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class SendBulkSubscriberMailRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('subscriber-list.send-mail') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'subject' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:10000'],
            'subscriber_ids' => ['nullable', 'array'],
            'subscriber_ids.*' => ['integer', 'exists:subscribers,id'],
            'all_active' => ['nullable', 'boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $allActive = filter_var($this->input('all_active', false), FILTER_VALIDATE_BOOLEAN);
            $subscriberIds = $this->input('subscriber_ids', []);

            if (! $allActive && empty($subscriberIds)) {
                $validator->errors()->add('subscriber_ids', 'Select at least one subscriber or choose all active subscribers.');
            }
        });
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
