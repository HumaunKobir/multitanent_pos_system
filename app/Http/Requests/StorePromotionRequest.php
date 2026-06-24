<?php

namespace App\Http\Requests;

use App\Enums\PromotionScope;
use App\Enums\PromotionType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

class StorePromotionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $type = $this->input('type');

        return [
            'name' => ['required', 'string', 'max:191'],
            'description' => ['nullable', 'string', 'max:1000'],
            'scope' => ['required', Rule::enum(PromotionScope::class)],
            'type' => ['required', Rule::enum(PromotionType::class)],
            'discount_value' => [
                Rule::requiredIf(in_array($type, [PromotionType::Percent->value, PromotionType::Flat->value, PromotionType::Bundle->value], true)),
                'nullable',
                'numeric',
                'min:0',
            ],
            'fixed_price' => [
                Rule::excludeIf(! in_array($type, [PromotionType::FixedPrice->value, PromotionType::Bundle->value], true)),
                Rule::requiredIf($type === PromotionType::FixedPrice->value),
                'nullable',
                'numeric',
                'min:0',
            ],
            'buy_qty' => [
                Rule::excludeIf($type !== PromotionType::BuyXGetY->value),
                'required',
                'integer',
                'min:1',
            ],
            'get_qty' => [
                Rule::excludeIf($type !== PromotionType::BuyXGetY->value),
                'required',
                'integer',
                'min:1',
            ],
            'get_discount_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'bundle_product_ids' => [
                Rule::excludeIf($type !== PromotionType::Bundle->value),
                'required',
                'array',
                'min:1',
            ],
            'bundle_product_ids.*' => [
                Rule::excludeIf($type !== PromotionType::Bundle->value),
                'integer',
                'exists:products,id',
            ],
            'target_ids' => ['required', 'array', 'min:1'],
            'target_ids.*' => ['integer', 'min:1'],
            'min_qty' => ['nullable', 'numeric', 'min:0.01'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => [
                'nullable',
                'date',
                Rule::when(
                    filled($this->input('starts_at')),
                    ['after_or_equal:starts_at'],
                ),
            ],
            'status' => ['required', 'boolean'],
            'priority' => ['required', 'integer', 'min:0'],
            'stack_with_product_discount' => ['required', 'boolean'],
            'stack_with_manual_line_discount' => ['required', 'boolean'],
            'stack_with_invoice_discount' => ['required', 'boolean'],
            'stack_with_special_discount' => ['required', 'boolean'],
            'exclusive' => ['required', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        foreach (['starts_at', 'ends_at'] as $field) {
            if ($this->has($field) && ($this->input($field) === '' || $this->input($field) === null)) {
                $this->merge([$field => null]);

                continue;
            }

            if ($this->filled($field)) {
                $parsed = Carbon::parse($this->input($field), config('app.timezone'));

                if ($field === 'ends_at' && $parsed->isStartOfDay()) {
                    $parsed = $parsed->copy()->endOfDay();
                }

                $this->merge([$field => $parsed->toDateTimeString()]);
            }
        }

        if ($this->has('min_qty') && ($this->input('min_qty') === '' || $this->input('min_qty') === null)) {
            $this->merge(['min_qty' => null]);
        }

        if ($this->has('status')) {
            $this->merge([
                'status' => filter_var($this->input('status'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? false,
            ]);
        }

        foreach ([
            'stack_with_product_discount',
            'stack_with_manual_line_discount',
            'stack_with_invoice_discount',
            'stack_with_special_discount',
            'exclusive',
        ] as $field) {
            if ($this->has($field)) {
                $this->merge([
                    $field => filter_var($this->input($field), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? false,
                ]);
            }
        }
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $type = $this->input('type');

            if ($type === PromotionType::Percent->value && (float) $this->input('discount_value', 0) > 100) {
                $validator->errors()->add('discount_value', 'Percent discount cannot exceed 100.');
            }
        });
    }
}
