<?php

namespace App\Http\Responses;

use App\Enums\BusinessSessionOpeningMethod;
use App\Services\BusinessSessionService;
use Illuminate\Http\JsonResponse;
use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;
use Symfony\Component\HttpFoundation\Response;

class LoginResponse implements LoginResponseContract
{
    public function __construct(private BusinessSessionService $businessSessions) {}

    public function toResponse($request): Response
    {
        $user = $request->user();

        if ($user && $request->boolean('start_business_session') && $user->can('business-session.start')) {
            try {
                $this->businessSessions->start($user, BusinessSessionOpeningMethod::StartedDuringLogin);
            } catch (\Throwable) {
                // Login still succeeds if session start fails (e.g. active session exists).
            }
        }

        $redirect = $user->usesBranchPanel()
            ? route('branch-panel.dashboard')
            : route('dashboard');

        return $request->wantsJson()
            ? new JsonResponse(['two_factor' => false], 200)
            : redirect()->intended($redirect);
    }
}
