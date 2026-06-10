<?php

namespace App\Http\Controllers\Auth;

use App\Enums\PasswordResetContext;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\PasswordResetOtpService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ForgotPasswordController extends Controller
{
    public function create(Request $request): Response
    {
        return Inertia::render('auth/forgot-password', [
            'status' => $request->session()->get('status'),
        ]);
    }

    public function store(Request $request, PasswordResetOtpService $passwordResetOtpService): RedirectResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
        ]);

        $email = strtolower($validated['email']);
        $user = User::query()->where('email', $email)->first();

        if ($user !== null) {
            if ($user->isProtectedFromPasswordReset()) {
                $message = $user->isSuperAdmin()
                    ? 'Password reset is not available for this account. Please update your password from Admin Profile in the admin panel.'
                    : 'Password reset is not available for this account. Please update your password from Branch Profile in the admin panel.';

                return back()->withErrors([
                    'email' => $message,
                ]);
            }

            $passwordResetOtpService->send(
                PasswordResetContext::User,
                $email,
                $user->name,
            );
        }

        $request->session()->put('password_reset.email', $email);
        $request->session()->put('password_reset.context', PasswordResetContext::User->value);

        return redirect()
            ->route('password.verify')
            ->with('status', 'If an account exists for that email, we have sent a verification code.');
    }
}
