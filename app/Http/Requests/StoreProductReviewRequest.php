<?php

namespace App\Http\Requests;

use App\Models\Customer;
use App\Models\Product;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreProductReviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'reviewer_name' => ['required', 'string', 'max:255'],
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'comment' => ['required', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $customer = $this->user('customer');

                if (! $customer instanceof Customer) {
                    return;
                }

                $slug = $this->route('product');

                if (! is_string($slug)) {
                    return;
                }

                $product = Product::query()
                    ->forStorefront()
                    ->where('slug', $slug)
                    ->first();

                if (! $product instanceof Product) {
                    return;
                }

                if (! $customer->hasReceivedProduct($product)) {
                    $validator->errors()->add(
                        'review',
                        'You can only review products you have purchased and received.',
                    );
                }
            },
        ];
    }
}
