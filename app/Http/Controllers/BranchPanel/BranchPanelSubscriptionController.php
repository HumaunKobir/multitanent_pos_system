<?php

namespace App\Http\Controllers\BranchPanel;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Services\BranchSubscriptionService;
use App\Support\BusinessSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class BranchPanelSubscriptionController extends Controller
{
    public function __construct(private BranchSubscriptionService $subscriptionService) {}

    public function index(): Response|RedirectResponse
    {
        $user = Auth::user();
        $branchId = $user?->branch_id;

        if (! $branchId || Branch::isMainBranch($branchId)) {
            return redirect()->route('branch-clients.index');
        }

        /** @var Branch $branch */
        $branch = Branch::query()->findOrFail($branchId);
        $subscription = $this->subscriptionService->getSubscriptionSummary($branch);

        $payments = $branch->subscriptionPayments()
            ->with('recordedBy:id,name')
            ->orderByDesc('id')
            ->get()
            ->map(fn ($p) => [
                'id' => $p->id,
                'amount' => (float) $p->amount,
                'payment_method' => $p->payment_method,
                'status' => $p->status ?? 'approved',
                'transaction_reference' => $p->transaction_reference,
                'billing_period_starts_at' => $p->billing_period_starts_at?->format('Y-m-d'),
                'billing_period_ends_at' => $p->billing_period_ends_at?->format('Y-m-d'),
                'paid_at' => $p->paid_at?->format('Y-m-d'),
                'recorded_by' => $p->recordedBy?->name ?? 'Online / Client',
                'notes' => $p->notes,
                'attachment_path' => $p->attachment_path,
                'attachment_url' => $p->attachment_url,
                'created_at' => $p->created_at?->format('Y-m-d H:i'),
            ]);

        return Inertia::render('branch-panel/subscription/index', [
            'branch' => [
                'id' => $branch->id,
                'name' => $branch->name,
                'phone' => $branch->phone,
                'address' => $branch->address,
            ],
            'subscription' => $subscription,
            'payments' => $payments,
            'paymentMethods' => [
                ['value' => 'bkash', 'label' => 'bKash'],
                ['value' => 'nagad', 'label' => 'Nagad'],
                ['value' => 'rocket', 'label' => 'Rocket'],
                ['value' => 'bank', 'label' => 'Bank Transfer'],
                ['value' => 'cash', 'label' => 'Cash Handover'],
                ['value' => 'other', 'label' => 'Other Online Channel'],
            ],
        ]);
    }

    public function submitPayment(Request $request): RedirectResponse
    {
        $user = Auth::user();
        $branchId = $user?->branch_id;

        if (! $branchId || Branch::isMainBranch($branchId)) {
            abort(403, 'Unauthorized');
        }

        /** @var Branch $branch */
        $branch = Branch::query()->findOrFail($branchId);

        $validated = $request->validate([
            'duration_days' => ['required', 'integer', 'min:1', 'max:3650'],
            'amount' => ['required', 'numeric', 'min:0'],
            'payment_method' => ['required', 'string', 'max:50'],
            'transaction_reference' => ['nullable', 'string', 'max:191'],
            'paid_at' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'attachment' => ['nullable', 'file', 'mimes:jpeg,png,jpg,webp,pdf', 'max:10240'],
        ]);

        $this->subscriptionService->submitPayment($branch, $validated, $user);

        return redirect()->route('branch-panel.subscription.index')
            ->with('success', 'Your subscription payment has been submitted for approval. SuperAdmin will verify the deposit and activate your renewal.');
    }
}
