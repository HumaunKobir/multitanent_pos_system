<?php

namespace App\Http\Controllers\Customer\Auth;

use App\Http\Controllers\Concerns\ManagesPasswordResetFlow;
use App\Http\Controllers\Controller;
use App\Models\Customer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Inertia\Inertia;
use Inertia\Response;

class CustomerResetPasswordController extends Controller
{
    use ManagesPasswordResetFlow;

    public function create(Request $request): Response|RedirectResponse
    {
        if ($redirect = $this->redirectIfMissingPasswordResetEmail($request, 'customer.password.request')) {
            return $redirect;
        }

        if ($redirect = $this->redirectIfMissingPasswordResetVerification($request, 'customer.password.verify')) {
            return $redirect;
        }

        return Inertia::render('frontend/customer/reset-password', [
            'email' => $this->passwordResetEmail($request),
            'status' => $request->session()->get('status'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        if ($redirect = $this->redirectIfMissingPasswordResetEmail($request, 'customer.password.request')) {
            return $redirect;
        }

        if ($redirect = $this->redirectIfMissingPasswordResetVerification($request, 'customer.password.verify')) {
            return $redirect;
        }

        $validated = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string', 'confirmed', PasswordRule::min(8)],
        ]);

        $email = strtolower($validated['email']);
        $sessionEmail = $this->passwordResetEmail($request);

        if ($sessionEmail === null || strtolower($sessionEmail) !== $email) {
            return back()->withErrors([
                'email' => 'Please start the password reset process again.',
            ]);
        }

        $customer = Customer::query()->where('email', $email)->first();

        if ($customer === null) {
            return back()->withErrors([
                'email' => 'We could not reset the password for this account.',
            ]);
        }

        $customer->forceFill([
            'password' => bcrypt($validated['password']),
        ])->save();

        $this->clearPasswordResetSession($request);

        return redirect()
            ->route('customer.login')
            ->with('status', 'Your password has been reset. You can sign in now.');
    }
}
