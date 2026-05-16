<?php

namespace App\Http\Controllers\Customer\Auth;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
            'phone' => 'required|string|max:30|unique:customers,phone',
            'password' => 'required|string|min:6|confirmed',
        ]);

        $customer = Customer::updateOrCreate(
            ['phone' => $validated['phone']],
            [
                'name' => $validated['name'],
                'email' => $validated['email'] ?? null,
                'password' => bcrypt($validated['password']),
            ]
        );

        auth('customer')->login($customer);

        return redirect()->route('customer.dashboard');
    }
}
