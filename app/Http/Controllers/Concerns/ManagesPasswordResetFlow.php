<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

trait ManagesPasswordResetFlow
{
    public const int VERIFIED_WINDOW_MINUTES = 15;

    protected function passwordResetEmail(Request $request): ?string
    {
        $email = $request->session()->get('password_reset.email');

        return is_string($email) && $email !== '' ? $email : null;
    }

    protected function redirectIfMissingPasswordResetEmail(Request $request, string $route): ?RedirectResponse
    {
        if ($this->passwordResetEmail($request) === null) {
            return redirect()->route($route);
        }

        return null;
    }

    protected function redirectIfMissingPasswordResetVerification(Request $request, string $route): ?RedirectResponse
    {
        if (! $this->passwordResetIsVerified($request)) {
            return redirect()->route($route);
        }

        return null;
    }

    protected function passwordResetIsVerified(Request $request): bool
    {
        $verifiedAt = $request->session()->get('password_reset.verified_at');

        if (! is_int($verifiedAt)) {
            return false;
        }

        return now()->timestamp <= ($verifiedAt + (self::VERIFIED_WINDOW_MINUTES * 60));
    }

    protected function markPasswordResetVerified(Request $request): void
    {
        $request->session()->put('password_reset.verified_at', now()->timestamp);
    }

    protected function clearPasswordResetSession(Request $request): void
    {
        $request->session()->forget([
            'password_reset.email',
            'password_reset.context',
            'password_reset.verified_at',
        ]);
    }
}
