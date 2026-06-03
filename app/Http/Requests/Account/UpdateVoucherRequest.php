<?php

namespace App\Http\Requests\Account;

use Illuminate\Validation\Rule;

class UpdateVoucherRequest extends StoreVoucherRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $rules = parent::rules();
        $voucher = $this->route('voucher');

        $rules['voucher_no'] = [
            'required',
            'string',
            'max:50',
            Rule::unique('vouchers', 'voucher_no')->ignore($voucher?->id),
        ];

        $rules['type'] = ['required', Rule::in([$voucher?->type->value])];

        return $rules;
    }
}
