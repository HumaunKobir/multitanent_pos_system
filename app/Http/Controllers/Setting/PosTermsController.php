<?php

namespace App\Http\Controllers\Setting;

use App\Http\Controllers\Controller;
use App\Http\Requests\Setting\UpdatePosTermsRequest;
use App\Models\Branch;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class PosTermsController extends Controller
{
    public function edit(): Response
    {
        $this->authorize('setting.pos-terms.view');

        $branch = $this->currentBranchOrFail();

        return Inertia::render('admin/setting/pos-terms/edit', [
            'posTerms' => [
                'content' => $branch->pos_terms_and_conditions ?? '',
                'branch_name' => $branch->name,
            ],
        ]);
    }

    public function update(UpdatePosTermsRequest $request): RedirectResponse
    {
        $branch = $this->currentBranchOrFail();

        $branch->update([
            'pos_terms_and_conditions' => $request->validated('content'),
        ]);

        return redirect()
            ->route('setting.pos-terms.edit')
            ->with('success', 'POS terms and conditions updated successfully.');
    }

    protected function currentBranchOrFail(): Branch
    {
        $branchId = Auth::user()?->branch_id;

        abort_unless($branchId, 403);

        return Branch::query()->findOrFail($branchId);
    }
}
