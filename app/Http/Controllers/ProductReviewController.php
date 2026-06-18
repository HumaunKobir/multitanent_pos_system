<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreProductReviewRequest;
use App\Models\Product;
use Illuminate\Http\RedirectResponse;

class ProductReviewController extends Controller
{
    public function store(StoreProductReviewRequest $request, Product $product): RedirectResponse
    {
        if (! auth('customer')->check()) {
            return back()->with('error', 'Please log in to leave a review.');
        }

        $product->reviews()->create([
            ...$request->validated(),
            'status' => 1,
        ]);

        return back()->with('success', 'Thank you for your review!');
    }
}
