<?php

namespace App\Http\Controllers\Setting;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\BusinessSetting;
use App\Services\BranchSubscriptionService;
use App\Support\BusinessSettings;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BusinessSetupController extends Controller
{
    public function __construct(private BranchSubscriptionService $subscriptionService) {}

    public function edit(): Response
    {
        $this->authorize('business-setup.view');

        $settings = BusinessSettings::all();

        return Inertia::render('admin/setting/business-setup/index', [
            'settings' => $settings,
            'tenancyEnabled' => (bool) config('tenancy.enabled'),
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
            'paymentMethods' => [
                ['value' => 'cash', 'label' => 'Cash'],
                ['value' => 'bank', 'label' => 'Bank Transfer'],
                ['value' => 'bkash', 'label' => 'bKash'],
                ['value' => 'nagad', 'label' => 'Nagad'],
                ['value' => 'rocket', 'label' => 'Rocket'],
                ['value' => 'other', 'label' => 'Other / Online'],
            ],
        ]);
    }

    public function updateSettings(Request $request): RedirectResponse
    {
        $this->authorize('business-setup.update');

        $validated = $request->validate([
            // Platform
            'system_name' => ['required', 'string', 'max:120'],
            'multi_tenant_enabled' => ['required', 'boolean'],

            // Subscription & Billing
            'subscription_billing_cycle' => ['required', 'string', 'in:monthly,quarterly,half_yearly,yearly,trial,lifetime,custom_days'],
            'subscription_billing_cycle_days' => ['required', 'integer', 'min:1', 'max:3650'],
            'subscription_payment_window_days' => ['required', 'integer', 'min:0', 'max:365'],
            'subscription_warning_days' => ['required', 'integer', 'min:0', 'max:365'],
            'subscription_grace_period_days' => ['required', 'integer', 'min:0', 'max:365'],
            'subscription_overdue_action' => ['required', 'string', 'in:warning_only,restrict_sales,read_only,suspend_branch'],
            'subscription_default_fee' => ['required', 'numeric', 'min:0'],
            'subscription_payment_instructions' => ['nullable', 'string', 'max:5000'],
            'subscription_warning_message' => ['nullable', 'string', 'max:2000'],
            'subscription_policy_terms' => ['nullable', 'string', 'max:10000'],

            // Superadmin Contact
            'superadmin_contact_name' => ['nullable', 'string', 'max:191'],
            'superadmin_contact_phone' => ['nullable', 'string', 'max:50'],
            'superadmin_contact_email' => ['nullable', 'string', 'email', 'max:191'],
        ]);

        $payload = [];
        foreach ($validated as $key => $value) {
            $payload[$key] = is_bool($value) ? ($value ? '1' : '0') : (string) ($value ?? '');
        }

        BusinessSetting::setMany($payload);

        // Apply tenancy preference for subsequent requests (env remains bootstrap fallback).
        config(['tenancy.enabled' => (bool) $validated['multi_tenant_enabled']]);

        return redirect()->route('setting.business-setup.edit')
            ->with('success', 'Business setup and policies updated successfully.');
    }

    public function updateBranchSubscription(Request $request, Branch $branch): RedirectResponse
    {
        $this->authorize('business-setup.update');

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
            'monthly', 'standard', 'basic', 'premium', 'enterprise' => 30,
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
        } else {
            $validated['custom_cycle_days'] = null;
        }

        $effectiveStart = ! empty($validated['subscription_starts_at'])
            ? Carbon::parse($validated['subscription_starts_at'])->startOfDay()
            : ($branch->subscription_starts_at ? Carbon::parse($branch->subscription_starts_at)->startOfDay() : Carbon::today());

        $validated['subscription_starts_at'] = $effectiveStart->toDateString();

        $latestApprovedPayment = $branch->subscriptionPayments()
            ->where('status', 'approved')
            ->latest('billing_period_ends_at')
            ->first();

        $startDateChanged = $branch->subscription_starts_at?->format('Y-m-d') !== $validated['subscription_starts_at'];
        $planChanged = ($branch->subscription_plan ?: 'standard') !== $cycle;

        if ($cycle === 'lifetime' || $validated['subscription_status'] === 'lifetime') {
            $validated['subscription_expires_at'] = null;
        } elseif (! empty($validated['subscription_expires_at'])) {
            $validated['subscription_expires_at'] = Carbon::parse($validated['subscription_expires_at'])->toDateString();
        } elseif ($latestApprovedPayment && $latestApprovedPayment->billing_period_ends_at) {
            $validated['subscription_expires_at'] = Carbon::parse($latestApprovedPayment->billing_period_ends_at)->toDateString();
        } elseif (! $startDateChanged && (! $planChanged || $cycle === 'custom_days') && $branch->subscription_expires_at) {
            $validated['subscription_expires_at'] = Carbon::parse($branch->subscription_expires_at)->toDateString();
        } elseif ($cycleDays !== null) {
            $validated['subscription_expires_at'] = $effectiveStart->copy()->addDays($cycleDays)->toDateString();
        }

        $branch->update($validated);
        $this->subscriptionService->syncOverdueLiability($branch->fresh());

        return redirect()->route('setting.business-setup.edit')
            ->with('success', "Subscription for {$branch->name} updated successfully.");
    }

    public function renewBranchSubscription(Request $request, Branch $branch): RedirectResponse
    {
        $this->authorize('business-setup.update');

        $validated = $request->validate([
            'pending_payment_id' => ['nullable', 'integer'],
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

        return redirect()->route('setting.business-setup.edit')
            ->with('success', "Subscription for {$branch->name} renewed successfully.");
    }

    public function branchPaymentHistory(Branch $branch): JsonResponse
    {
        $this->authorize('business-setup.view');

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
                'recorded_by' => $p->recordedBy?->name ?? 'System',
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
