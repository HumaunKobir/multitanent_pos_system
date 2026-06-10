<?php

namespace App\Http\Controllers\Auth;

use App\Enums\PasswordResetContext;
use App\Http\Controllers\Concerns\ManagesPasswordResetFlow;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\PasswordResetOtpService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class VerifyPasswordResetController extends Controller
{
    use ManagesPasswordResetFlow;

    public function create(Request $request): Response|RedirectResponse
    {
        if ($redirect = $this->redirectIfMissingPasswordResetEmail($request, 'password.request')) {
            return $redirect;
        }

        return Inertia::render('auth/verify-password-reset', [
            'email' => $this->passwordResetEmail($request),
            'status' => $request->session()->get('status'),
        ]);
    }

    public function store(Request $request, PasswordResetOtpService $passwordResetOtpService): RedirectResponse
    {
        if ($redirect = $this->redirectIfMissingPasswordResetEmail($request, 'password.request')) {
            return $redirect;
        }

        $validated = $request->validate([
            'email' => ['required', 'email'],
            'otp' => ['required', 'string', 'size:'.PasswordResetOtpService::OTP_LENGTH],
        ]);

        $email = strtolower($validated['email']);
        $sessionEmail = $this->passwordResetEmail($request);

        if ($sessionEmail === null || strtolower($sessionEmail) !== $email) {
            return back()->withErrors([
                'email' => 'Please request a new verification code.',
            ]);
        }

        $user = User::query()->where('email', $email)->first();

        if ($user === null || $user->isProtectedFromPasswordReset()) {
            return back()->withErrors([
                'email' => 'We could not verify this account.',
            ]);
        }

        if (! $passwordResetOtpService->verify(PasswordResetContext::User, $email, $validated['otp'])) {
            return back()->withErrors([
                'otp' => 'The verification code is invalid or has expired.',
            ]);
        }

        $this->markPasswordResetVerified($request);

        return redirect()
            ->route('password.reset')
            ->with('status', 'Code verified. Now set your new password.');
    }
}
