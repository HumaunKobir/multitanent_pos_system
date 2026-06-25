<?php

namespace App\Http\Controllers\Customer\Auth;

use App\Enums\CustomerRegistrationType;
use App\Http\Controllers\Controller;
use App\Models\Customer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class CustomerRegisterController extends Controller
{
    public function showRegisterForm(): Response
    {
        return Inertia::render('frontend/customer/register');
    }

    public function register(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'nullable|email|unique:customers,email',
            'phone' => [
                'required',
                'string',
                'max:30',
                Rule::unique('customers', 'phone')->where(function ($query) {
                    $query->where('registration_type', CustomerRegistrationType::Online->value);
                }),
            ],
            'password' => 'required|string|min:6|confirmed',
        ]);

        $existingOnline = Customer::query()
            ->where('phone', $validated['phone'])
            ->where('registration_type', CustomerRegistrationType::Online)
            ->first();

        if ($existingOnline) {
            return back()->withErrors(['phone' => 'This phone number is already registered.'])->onlyInput('name', 'email', 'phone');
        }

        $existing = Customer::query()
            ->where('phone', $validated['phone'])
            ->where('registration_type', CustomerRegistrationType::Offline)
            ->first();

        if ($existing) {
            $existing->update([
                'name' => $validated['name'],
                'email' => $validated['email'] ?? $existing->email,
                'password' => bcrypt($validated['password']),
                'registration_type' => CustomerRegistrationType::Online,
            ]);

            auth('customer')->login($existing);

            return redirect()->route('customer.dashboard');
        }

        $customer = Customer::create([
            'name' => $validated['name'],
            'email' => $validated['email'] ?? null,
            'phone' => $validated['phone'],
            'password' => bcrypt($validated['password']),
            'registration_type' => CustomerRegistrationType::Online,
        ]);

        auth('customer')->login($customer);

        return redirect()->route('customer.dashboard');
    }
}
