<?php

namespace App\Http\Controllers\Setting;

use App\Http\Controllers\Controller;
use App\Http\Requests\Setting\UpdateBranchProfileRequest;
use App\Models\Branch;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class BranchProfileController extends Controller
{
    public function edit(): Response
    {
        $branch = $this->currentBranchOrFail();
        $user = Auth::user();

        return Inertia::render('admin/setting/branch-profile/index', [
            'branch' => [
                'id' => $branch->id,
                'name' => $branch->name,
                'phone' => $branch->phone,
                'address' => $branch->address,
            ],
            'account' => [
                'email' => $user?->email,
            ],
        ]);
    }

    public function update(UpdateBranchProfileRequest $request): RedirectResponse
    {
        $branch = $this->currentBranchOrFail();
        $user = Auth::user();
        $validated = $request->validated();

        $branch->update([
            'name' => $validated['name'],
            'phone' => $validated['phone'] ?? null,
            'address' => $validated['address'] ?? null,
        ]);

        $userPayload = [
            'email' => $validated['email'],
        ];

        if (! empty($validated['password'])) {
            $userPayload['password'] = $validated['password'];
        }

        $user?->update($userPayload);

        return redirect()
            ->route('setting.branch-profile.edit')
            ->with('success', 'Branch profile updated successfully.');
    }

    protected function currentBranchOrFail(): Branch
    {
        $branchId = Auth::user()?->branch_id;

        abort_unless($branchId, 403);

        return Branch::query()->findOrFail($branchId);
    }
}
