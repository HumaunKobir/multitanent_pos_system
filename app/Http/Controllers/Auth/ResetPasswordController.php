<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Concerns\ManagesPasswordResetFlow;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Inertia\Inertia;
use Inertia\Response;

class ResetPasswordController extends Controller
{
    use ManagesPasswordResetFlow;

    public function create(Request $request): Response|RedirectResponse
    {
        if ($redirect = $this->redirectIfMissingPasswordResetEmail($request, 'password.request')) {
            return $redirect;
        }

        if ($redirect = $this->redirectIfMissingPasswordResetVerification($request, 'password.verify')) {
            return $redirect;
        }

        return Inertia::render('auth/reset-password', [
            'email' => $this->passwordResetEmail($request),
            'status' => $request->session()->get('status'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        if ($redirect = $this->redirectIfMissingPasswordResetEmail($request, 'password.request')) {
            return $redirect;
        }

        if ($redirect = $this->redirectIfMissingPasswordResetVerification($request, 'password.verify')) {
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

        $user = User::query()->where('email', $email)->first();

        if ($user === null || $user->isProtectedFromPasswordReset()) {
            return back()->withErrors([
                'email' => 'We could not reset the password for this account.',
            ]);
        }

        $user->forceFill([
            'password' => $validated['password'],
        ])->save();

        $this->clearPasswordResetSession($request);

        return redirect()
            ->route('login')
            ->with('status', 'Your password has been reset. You can sign in now.');
    }
}
