<?php

namespace App\Http\Controllers\BranchPanel;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Services\BranchPaymentAccountService;
use App\Services\BranchSubscriptionService;
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

        $paymentMethods = BranchPaymentAccountService::listForBranch($branchId)
            ->map(fn ($acc) => [
                'id' => $acc->id,
                'code' => $acc->code,
                'value' => $acc->name,
                'label' => $acc->name,
            ])
            ->values();

        if ($paymentMethods->isEmpty()) {
            $paymentMethods = collect([
                ['value' => 'Cash in Hand', 'label' => 'Cash in Hand'],
                ['value' => 'bKash', 'label' => 'bKash'],
                ['value' => 'Nagad', 'label' => 'Nagad'],
                ['value' => 'SSLCommerz', 'label' => 'SSLCommerz'],
            ]);
        }

        return Inertia::render('branch-panel/subscription/index', [
            'branch' => [
                'id' => $branch->id,
                'name' => $branch->name,
                'phone' => $branch->phone,
                'address' => $branch->address,
            ],
            'subscription' => $subscription,
            'payments' => $payments,
            'paymentMethods' => $paymentMethods,
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
            'payment_account_id' => ['nullable', 'integer'],
            'transaction_reference' => ['nullable', 'string', 'max:191'],
            'paid_at' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'attachment' => ['nullable', 'file', 'mimes:jpeg,png,jpg,webp,pdf', 'max:10240'],
        ]);

        if (! empty($validated['payment_account_id'])) {
            abort_unless(
                BranchPaymentAccountService::isValid((int) $validated['payment_account_id'], $branchId),
                422,
                'Invalid payment channel account for this branch.',
            );
        } else {
            $matched = BranchPaymentAccountService::listForBranch($branchId)
                ->first(fn ($acc) => $acc->name === $validated['payment_method'] || $acc->code === $validated['payment_method']);

            if ($matched !== null) {
                $validated['payment_account_id'] = $matched->id;
            }
        }

        $this->subscriptionService->submitPayment($branch, $validated, $user);

        return redirect()->route('branch-panel.subscription.index')
            ->with('success', 'Your subscription payment has been submitted for approval. SuperAdmin will verify the deposit and activate your renewal.');
    }

    public function invoices(): \Illuminate\Http\JsonResponse
    {
        $user = Auth::user();
        $branchId = $user?->branch_id;

        if (! $branchId || Branch::isMainBranch($branchId)) {
            abort(403, 'Unauthorized');
        }

        /** @var Branch $branch */
        $branch = Branch::query()->findOrFail($branchId);

        $invoices = $branch->subscriptionInvoices()
            ->with('paymentAllocations.payment')
            ->orderByDesc('id')
            ->get()
            ->map(fn ($inv) => [
                'id' => $inv->id,
                'invoice_number' => $inv->invoice_number,
                'billing_period_starts_at' => $inv->billing_period_starts_at?->format('Y-m-d'),
                'billing_period_ends_at' => $inv->billing_period_ends_at?->format('Y-m-d'),
                'due_date' => $inv->due_date?->format('Y-m-d'),
                'total_amount' => (float) $inv->total_amount,
                'paid_amount' => (float) $inv->paid_amount,
                'due_amount' => (float) $inv->due_amount,
                'status' => $inv->status,
                'notes' => $inv->notes,
                'created_at' => $inv->created_at?->format('Y-m-d H:i'),
                'allocations' => $inv->paymentAllocations->map(fn ($alloc) => [
                    'id' => $alloc->id,
                    'amount' => (float) $alloc->amount,
                    'payment_id' => $alloc->branch_subscription_payment_id,
                    'payment_method' => $alloc->payment?->payment_method,
                    'paid_at' => $alloc->payment?->paid_at?->format('Y-m-d'),
                ]),
            ]);

        return response()->json([
            'invoices' => $invoices,
        ]);
    }

    public function securityDeposits(): \Illuminate\Http\JsonResponse
    {
        $user = Auth::user();
        $branchId = $user?->branch_id;

        if (! $branchId || Branch::isMainBranch($branchId)) {
            abort(403, 'Unauthorized');
        }

        /** @var Branch $branch */
        $branch = Branch::query()->findOrFail($branchId);

        $deposits = $branch->securityDeposits()
            ->with('recordedBy:id,name')
            ->orderByDesc('id')
            ->get()
            ->map(fn ($d) => [
                'id' => $d->id,
                'amount' => (float) $d->amount,
                'payment_method' => $d->payment_method,
                'transaction_reference' => $d->transaction_reference,
                'paid_at' => $d->paid_at?->format('Y-m-d'),
                'status' => $d->status,
                'notes' => $d->notes,
                'attachment_path' => $d->attachment_path,
                'attachment_url' => $d->attachment_url,
                'created_at' => $d->created_at?->format('Y-m-d H:i'),
            ]);

        return response()->json([
            'security_deposits' => $deposits,
            'total_security_deposit' => (float) $deposits->where('status', 'approved')->sum('amount'),
        ]);
    }
}
