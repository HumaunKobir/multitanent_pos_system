<?php

namespace App\Http\Controllers\Setting;

use App\Http\Controllers\Controller;
use App\Http\Requests\Setting\UpdateAdminProfileRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class AdminProfileController extends Controller
{
    public function edit(): Response
    {
        abort_unless(Auth::user()?->isSuperAdmin(), 403);

        $user = Auth::user();

        return Inertia::render('admin/setting/admin-profile/index', [
            'account' => [
                'name' => $user?->name,
                'email' => $user?->email,
                'phone' => $user?->phone,
            ],
        ]);
    }

    public function update(UpdateAdminProfileRequest $request): RedirectResponse
    {
        $user = Auth::user();
        abort_unless($user?->isSuperAdmin(), 403);

        $validated = $request->validated();

        $payload = [
            'name' => $validated['name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'],
        ];

        if (! empty($validated['password'])) {
            $payload['password'] = $validated['password'];
        }

        $user->update($payload);

        return redirect()
            ->route('setting.admin-profile.edit')
            ->with('success', 'Admin profile updated successfully.');
    }
}
