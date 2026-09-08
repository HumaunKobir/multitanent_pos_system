<?php

namespace App\Http\Controllers\Setting;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\BranchSubscriptionPayment;
use App\Services\BranchSubscriptionService;
use App\Support\BusinessSettings;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BranchClientController extends Controller
{
    public function __construct(private BranchSubscriptionService $subscriptionService) {}

    public function index(Request $request): Response
    {
        $this->authorize('branch.view');

        $search = $request->input('search');
        $statusFilter = $request->input('status', 'all');

        $query = Branch::query()->orderBy('id');

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('address', 'like', "%{$search}%");
            });
        }

        $allBranches = $query->get()->map(function (Branch $branch) {
            $isMain = Branch::isMainBranch($branch->id);
            $sub = $this->subscriptionService->getSubscriptionSummary($branch);

            $latestPayment = BranchSubscriptionPayment::with('recordedBy:id,name')
                ->where('branch_id', $branch->id)
                ->latest('id')
                ->first();

            return [
                'id' => $branch->id,
                'name' => $branch->name,
                'phone' => $branch->phone,
                'address' => $branch->address,
                'status' => $branch->status?->value ?? (int) $branch->status,
                'is_main_branch' => $isMain,
                'custom_cycle_days' => $branch->custom_cycle_days,
                'custom_overdue_action' => $branch->custom_overdue_action,
                'subscription_notes' => $branch->subscription_notes,
                'custom_grace_period_days' => $branch->custom_grace_period_days,
                'custom_warning_days' => $branch->custom_warning_days,
                'subscription_fee' => $branch->subscription_fee,
                'subscription' => $sub,
                'latest_payment' => $latestPayment ? [
                    'id' => $latestPayment->id,
                    'amount' => (float) $latestPayment->amount,
                    'payment_method' => $latestPayment->payment_method,
                    'status' => $latestPayment->status ?? 'approved',
                    'transaction_reference' => $latestPayment->transaction_reference,
                    'billing_period_starts_at' => $latestPayment->billing_period_starts_at ? Carbon::parse($latestPayment->billing_period_starts_at)->format('Y-m-d') : null,
                    'billing_period_ends_at' => $latestPayment->billing_period_ends_at ? Carbon::parse($latestPayment->billing_period_ends_at)->format('Y-m-d') : null,
                    'paid_at' => $latestPayment->paid_at ? Carbon::parse($latestPayment->paid_at)->format('Y-m-d') : null,
                    'attachment_path' => $latestPayment->attachment_path,
                    'attachment_url' => $latestPayment->attachment_url,
                    'recorded_by' => $latestPayment->recordedBy?->name ?? 'Client / System',
                    'notes' => $latestPayment->notes,
                    'created_at' => $latestPayment->created_at?->diffForHumans(),
                ] : null,
            ];
        });

        // Calculate summary statistics across non-main branches
        $clientBranches = $allBranches->filter(fn ($b) => ! $b['is_main_branch']);
        $totalClients = $clientBranches->count();
        $pendingApprovals = $clientBranches->filter(fn ($b) => ($b['subscription']['has_pending_payment'] ?? false) || ($b['latest_payment']['status'] ?? '') === 'pending')->count();
        $activeClients = $clientBranches->filter(fn ($b) => $b['subscription']['computed_status'] === 'active')->count();
        $expiringSoon = $clientBranches->filter(fn ($b) => $b['subscription']['computed_status'] === 'expiring_soon')->count();
        $overdueClients = $clientBranches->filter(fn ($b) => $b['subscription']['is_overdue'])->count();
        $suspendedClients = $clientBranches->filter(fn ($b) => $b['subscription']['is_suspended'])->count();
        $lifetimeClients = $clientBranches->filter(fn ($b) => $b['subscription']['status'] === 'lifetime')->count();
        $totalOverdueDue = $clientBranches->sum(fn ($b) => $b['subscription']['total_overdue_fee'] ?? 0);

        // Filter branches if filter is selected
        $filteredBranches = $allBranches->filter(function ($b) use ($statusFilter) {
            if ($statusFilter === 'all') {
                return true;
            }
            if ($statusFilter === 'pending_approval') {
                return ($b['subscription']['has_pending_payment'] ?? false) || ($b['latest_payment']['status'] ?? '') === 'pending';
            }
            if ($statusFilter === 'active') {
                return $b['subscription']['computed_status'] === 'active';
            }
            if ($statusFilter === 'expiring_soon') {
                return $b['subscription']['computed_status'] === 'expiring_soon';
            }
            if ($statusFilter === 'overdue') {
                return $b['subscription']['is_overdue'];
            }
            if ($statusFilter === 'suspended') {
                return $b['subscription']['is_suspended'];
            }
            if ($statusFilter === 'lifetime') {
                return $b['subscription']['status'] === 'lifetime';
            }

            return true;
        })->values();

        return Inertia::render('admin/branch-client/index', [
            'branches' => $filteredBranches,
            'stats' => [
                'total_clients' => $totalClients,
                'pending_approvals' => $pendingApprovals,
                'active_clients' => $activeClients,
                'expiring_soon' => $expiringSoon,
                'overdue_clients' => $overdueClients,
                'suspended_clients' => $suspendedClients,
                'lifetime_clients' => $lifetimeClients,
                'total_overdue_due' => $totalOverdueDue,
            ],
            'filters' => [
                'search' => $search,
                'status' => $statusFilter,
            ],
            'billingCycles' => [
                ['value' => 'monthly', 'label' => 'Monthly (30 Days)', 'days' => 30],
                ['value' => 'quarterly', 'label' => 'Quarterly (90 Days)', 'days' => 90],
                ['value' => 'half_yearly', 'label' => 'Half-Yearly (180 Days)', 'days' => 180],
                ['value' => 'yearly', 'label' => 'Yearly (365 Days)', 'days' => 365],
                ['value' => 'trial', 'label' => 'Free Trial (14 Days)', 'days' => 14],
                ['value' => 'lifetime', 'label' => 'Lifetime (Unlimited)', 'days' => null],
                ['value' => 'custom_days', 'label' => 'Custom Days Cycle', 'days' => 30],
            ],
            'overdueActions' => [
                ['value' => 'warning_only', 'label' => 'Warning Alert Only (Banner notification without blocking)'],
                ['value' => 'restrict_sales', 'label' => 'Restrict Sales / POS (Disable POS checkout & sales creation)'],
                ['value' => 'read_only', 'label' => 'Read-Only Mode (Disable all create/edit/delete operations)'],
                ['value' => 'suspend_branch', 'label' => 'Full Account Suspension (Lock branch panel completely)'],
            ],
            'paymentMethods' => \App\Services\BranchPaymentAccountService::listForBranch(null)
                ->map(fn ($acc) => [
                    'id' => $acc->id,
                    'code' => $acc->code,
                    'value' => $acc->name,
                    'label' => $acc->name,
                ])
                ->values(),
        ]);
    }

    public function update(Request $request, Branch $branch): RedirectResponse
    {
        $this->authorize('branch.update');

        $validated = $request->validate([
            'subscription_plan' => ['required', 'string', 'in:monthly,quarterly,half_yearly,yearly,trial,lifetime,custom_days,basic,standard,premium,enterprise'],
            'subscription_status' => ['required', 'string', 'in:active,suspended,trial,lifetime'],
            'subscription_fee' => ['nullable', 'numeric', 'min:0'],
            'subscription_starts_at' => ['nullable', 'date'],
            'subscription_expires_at' => ['nullable', 'date'],
            'custom_cycle_days' => ['nullable', 'integer', 'min:1', 'max:3650'],
            'custom_grace_period_days' => ['nullable', 'integer', 'min:0', 'max:365'],
            'custom_warning_days' => ['nullable', 'integer', 'min:0', 'max:365'],
            'custom_overdue_action' => ['nullable', 'string', 'in:warning_only,restrict_sales,read_only,suspend_branch'],
            'subscription_notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $cycle = $validated['subscription_plan'] ?? 'monthly';
        $customCycleDays = ! empty($validated['custom_cycle_days']) ? (int) $validated['custom_cycle_days'] : null;
        $cycleDays = match ($cycle) {
            'monthly' => 30,
            'quarterly' => 90,
            'half_yearly' => 180,
            'yearly' => 365,
            'trial' => 14,
            'lifetime' => null,
            'custom_days' => $customCycleDays ?: 30,
            default => BusinessSettings::getInt('subscription_billing_cycle_days', 30),
        };

        if ($cycle === 'custom_days') {
            $validated['custom_cycle_days'] = $customCycleDays ?: 30;
        }

        $effectiveStart = ! empty($validated['subscription_starts_at'])
            ? Carbon::parse($validated['subscription_starts_at'])->startOfDay()
            : ($branch->subscription_starts_at ? Carbon::parse($branch->subscription_starts_at)->startOfDay() : Carbon::today());

        $validated['subscription_starts_at'] = $effectiveStart->toDateString();

        if ($cycle === 'lifetime' || $validated['subscription_status'] === 'lifetime') {
            $validated['subscription_expires_at'] = null;
        } elseif (! empty($validated['subscription_expires_at'])) {
            $validated['subscription_expires_at'] = Carbon::parse($validated['subscription_expires_at'])->toDateString();
        } elseif ($branch->subscription_expires_at !== null) {
            // Strictly retain existing expiration date so overdue dues and active validity are preserved!
            unset($validated['subscription_expires_at']);
        } elseif ($cycleDays !== null) {
            // Only calculate initial expiry for a brand new branch that has no expiry date yet
            $validated['subscription_expires_at'] = $effectiveStart->copy()->addDays($cycleDays)->toDateString();
        }

        $branch->update($validated);

        return redirect()->route('branch-clients.index')
            ->with('success', "Subscription configuration for {$branch->name} updated successfully.");
    }

    public function renew(Request $request, Branch $branch): RedirectResponse
    {
        $this->authorize('branch.update');

        $validated = $request->validate([
            'duration_days' => ['required', 'integer', 'min:1', 'max:3650'],
            'amount' => ['required', 'numeric', 'min:0'],
            'payment_method' => ['required', 'string', 'max:50'],
            'transaction_reference' => ['nullable', 'string', 'max:191'],
            'paid_at' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'attachment' => ['nullable', 'file', 'mimes:jpeg,png,jpg,webp,pdf', 'max:10240'],
            'existing_attachment_path' => ['nullable', 'string', 'max:500'],
        ]);

        $this->subscriptionService->renew($branch, $validated, $request->user());

        return redirect()->route('branch-clients.index')
            ->with('success', "Subscription payment for {$branch->name} recorded successfully.");
    }

    public function payments(Branch $branch): JsonResponse
    {
        $this->authorize('branch.view');

        $payments = $branch->subscriptionPayments()
            ->with('recordedBy:id,name')
            ->orderByDesc('id')
            ->get()
            ->map(fn ($p) => [
                'id' => $p->id,
                'amount' => (float) $p->amount,
                'payment_method' => $p->payment_method,
                'transaction_reference' => $p->transaction_reference,
                'billing_period_starts_at' => $p->billing_period_starts_at?->format('Y-m-d'),
                'billing_period_ends_at' => $p->billing_period_ends_at?->format('Y-m-d'),
                'paid_at' => $p->paid_at?->format('Y-m-d'),
                'recorded_by' => $p->recordedBy?->name ?? 'SuperAdmin',
                'notes' => $p->notes,
                'attachment_path' => $p->attachment_path,
                'attachment_url' => $p->attachment_url,
                'created_at' => $p->created_at?->format('Y-m-d H:i'),
            ]);

        return response()->json([
            'branch' => [
                'id' => $branch->id,
                'name' => $branch->name,
            ],
            'payments' => $payments,
        ]);
    }
}
