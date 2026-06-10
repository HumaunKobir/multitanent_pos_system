<?php

namespace App\Http\Controllers\Customer\Auth;

use App\Enums\PasswordResetContext;
use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Services\PasswordResetOtpService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CustomerForgotPasswordController extends Controller
{
    public function create(Request $request): Response
    {
        return Inertia::render('frontend/customer/forgot-password', [
            'status' => $request->session()->get('status'),
        ]);
    }

    public function store(Request $request, PasswordResetOtpService $passwordResetOtpService): RedirectResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
        ]);

        $email = strtolower($validated['email']);
        $customer = Customer::query()->where('email', $email)->first();

        if ($customer !== null && filled($customer->email)) {
            $passwordResetOtpService->send(
                PasswordResetContext::Customer,
                $email,
                $customer->name,
            );
        }

        $request->session()->put('password_reset.email', $email);
        $request->session()->put('password_reset.context', PasswordResetContext::Customer->value);

        return redirect()
            ->route('customer.password.verify')
            ->with('status', 'If an account exists for that email, we have sent a verification code.');
    }
}
