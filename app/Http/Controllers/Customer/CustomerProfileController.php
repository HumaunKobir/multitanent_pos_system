<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class CustomerProfileController extends Controller
{
    public function edit(): Response
    {
        /** @var Customer $customer */
        $customer = auth('customer')->user();

        return Inertia::render('frontend/customer/profile', [
            'customer' => $customer->only([
                'id', 'name', 'email', 'phone', 'address', 'image', 'image_url',
            ]),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        /** @var Customer $customer */
        $customer = auth('customer')->user();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255', Rule::unique('customers', 'email')->ignore($customer->id)],
            'phone' => ['required', 'string', 'max:30', Rule::unique('customers', 'phone')->ignore($customer->id)],
            'address' => ['nullable', 'string', 'max:500'],
            'password' => ['nullable', 'string', 'min:6', 'confirmed'],
            'image' => ['nullable', 'image', 'mimes:jpeg,png,jpg,gif,webp', 'max:2048'],
            'remove_image' => ['sometimes', 'boolean'],
        ]);

        if ($request->boolean('remove_image')) {
            $this->deleteImage($customer);
            $validated['image'] = null;
        } elseif ($request->hasFile('image')) {
            $this->deleteImage($customer);
            $validated['image'] = $request->file('image')->store('customers', 'public');
        } else {
            unset($validated['image']);
        }

        unset($validated['remove_image']);

        if (empty($validated['password'])) {
            unset($validated['password']);
        } else {
            $validated['password'] = bcrypt($validated['password']);
        }

        $customer->update($validated);

        return back()->with('success', 'Profile updated successfully.');
    }

    private function deleteImage(Customer $customer): void
    {
        if ($customer->image) {
            Storage::disk('public')->delete($customer->image);
        }
    }
}
