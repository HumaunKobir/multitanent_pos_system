<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\BranchSubscriptionPayment;
use App\Models\User;
use App\Support\BusinessSettings;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;

class BranchSubscriptionService
{
    public function __construct(
        protected BranchSubscriptionAccountingService $accounting,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function getSubscriptionSummary(Branch $branch): array
    {
        $isMainBranch = Branch::isMainBranch($branch->id);

        if ($isMainBranch) {
            return [
                'branch_id' => $branch->id,
                'branch_name' => $branch->name,
                'is_main_branch' => true,
                'plan' => 'enterprise',
                'status' => 'lifetime',
                'computed_status' => 'lifetime',
                'fee' => 0.0,
                'starts_at' => null,
                'expires_at' => null,
                'last_paid_at' => null,
                'days_remaining' => 9999,
                'overdue_days' => 0,
                'grace_days_remaining' => 0,
                'is_active' => true,
                'is_expiring_soon' => false,
                'is_overdue' => false,
                'is_in_grace_period' => false,
                'is_suspended' => false,
                'warning_days' => 0,
                'grace_period_days' => 0,
                'overdue_action' => 'none',
                'warning_message' => null,
                'payment_instructions' => '',
                'superadmin_contact' => [
                    'name' => BusinessSettings::get('superadmin_contact_name'),
                    'phone' => BusinessSettings::get('superadmin_contact_phone'),
                    'email' => BusinessSettings::get('superadmin_contact_email'),
                ],
            ];
        }

        $plan = $branch->subscription_plan ?: 'standard';
        $rawStatus = $branch->subscription_status ?: 'active';
        $defaultFee = BusinessSettings::getFloat('subscription_default_fee', 1500);
        $fee = $branch->subscription_fee !== null ? (float) $branch->subscription_fee : $defaultFee;

        $warningDays = $branch->custom_warning_days !== null
            ? (int) $branch->custom_warning_days
            : BusinessSettings::getInt('subscription_warning_days', 5);

        $gracePeriodDays = $branch->custom_grace_period_days !== null
            ? (int) $branch->custom_grace_period_days
            : BusinessSettings::getInt('subscription_grace_period_days', 7);

        $overdueAction = filled($branch->custom_overdue_action)
            ? $branch->custom_overdue_action
            : BusinessSettings::get('subscription_overdue_action', 'restrict_sales');

        $startsAt = $branch->subscription_starts_at ? Carbon::parse($branch->subscription_starts_at)->format('Y-m-d') : null;
        $expiresAt = $branch->subscription_expires_at ? Carbon::parse($branch->subscription_expires_at)->format('Y-m-d') : null;
        $lastPaidAt = $branch->subscription_last_paid_at ? Carbon::parse($branch->subscription_last_paid_at)->format('Y-m-d') : null;

        if ($rawStatus === 'lifetime') {
            return [
                'branch_id' => $branch->id,
                'branch_name' => $branch->name,
                'is_main_branch' => false,
                'plan' => $plan,
                'status' => 'lifetime',
                'computed_status' => 'lifetime',
                'fee' => $fee,
                'starts_at' => $startsAt,
                'expires_at' => null,
                'last_paid_at' => $lastPaidAt,
                'days_remaining' => 9999,
                'overdue_days' => 0,
                'grace_days_remaining' => 0,
                'is_active' => true,
                'is_expiring_soon' => false,
                'is_overdue' => false,
                'is_in_grace_period' => false,
                'is_suspended' => false,
                'warning_days' => $warningDays,
                'grace_period_days' => $gracePeriodDays,
                'overdue_action' => $overdueAction,
                'warning_message' => null,
                'payment_instructions' => BusinessSettings::get('subscription_payment_instructions', ''),
                'superadmin_contact' => [
                    'name' => BusinessSettings::get('superadmin_contact_name'),
                    'phone' => BusinessSettings::get('superadmin_contact_phone'),
                    'email' => BusinessSettings::get('superadmin_contact_email'),
                ],
            ];
        }

        if ($rawStatus === 'suspended') {
            return [
                'branch_id' => $branch->id,
                'branch_name' => $branch->name,
                'is_main_branch' => false,
                'plan' => $plan,
                'status' => 'suspended',
                'computed_status' => 'suspended',
                'fee' => $fee,
                'starts_at' => $startsAt,
                'expires_at' => $expiresAt,
                'last_paid_at' => $lastPaidAt,
                'days_remaining' => -1,
                'overdue_days' => 1,
                'grace_days_remaining' => 0,
                'is_active' => false,
                'is_expiring_soon' => false,
                'is_overdue' => true,
                'is_in_grace_period' => false,
                'is_suspended' => true,
                'warning_days' => $warningDays,
                'grace_period_days' => $gracePeriodDays,
                'overdue_action' => $overdueAction,
                'warning_message' => 'Your subscription has been suspended by the administrator. Please contact support to reactivate.',
                'payment_instructions' => BusinessSettings::get('subscription_payment_instructions', ''),
                'superadmin_contact' => [
                    'name' => BusinessSettings::get('superadmin_contact_name'),
                    'phone' => BusinessSettings::get('superadmin_contact_phone'),
                    'email' => BusinessSettings::get('superadmin_contact_email'),
                ],
            ];
        }

        // Calculate based on expiry date
        $today = Carbon::today();
        $expiryDate = $branch->subscription_expires_at ? Carbon::parse($branch->subscription_expires_at)->startOfDay() : null;

        if ($expiryDate === null) {
            // Default 30 days active if not initialized
            $expiryDate = $today->copy()->addDays(30);
            $expiresAt = $expiryDate->format('Y-m-d');
        }

        $daysRemaining = (int) $today->diffInDays($expiryDate, false);

        $computedStatus = 'active';
        $isActive = true;
        $isExpiringSoon = false;
        $isOverdue = false;
        $isInGracePeriod = false;
        $isSuspended = false;
        $overdueDays = 0;
        $graceDaysRemaining = 0;
        $warningMessage = null;

        if ($daysRemaining > $warningDays) {
            $computedStatus = 'active';
            $isActive = true;
        } elseif ($daysRemaining >= 0 && $daysRemaining <= $warningDays) {
            $computedStatus = 'expiring_soon';
            $isActive = true;
            $isExpiringSoon = true;
            $warningMessage = $this->formatWarningTemplate(
                BusinessSettings::get('subscription_warning_message', ''),
                $branch->name,
                $daysRemaining,
                $expiresAt,
                $fee
            );
        } else {
            // Overdue
            $isOverdue = true;
            $overdueDays = abs($daysRemaining);

            if ($gracePeriodDays > 0 && $overdueDays < $gracePeriodDays) {
                $computedStatus = 'grace_period';
                $isActive = true;
                $isInGracePeriod = true;
                $graceDaysRemaining = $gracePeriodDays - $overdueDays;
                $warningMessage = "Your branch subscription payment is overdue by {$overdueDays} day(s). You have {$graceDaysRemaining} grace day(s) remaining before {$this->formatOverdueActionLabel($overdueAction)}.";
            } else {
                $isInGracePeriod = false;
                $graceDaysRemaining = 0;

                if ($overdueAction === 'suspend_branch') {
                    $computedStatus = 'suspended';
                    $isActive = false;
                    $isSuspended = true;
                    $warningMessage = "Your branch subscription grace period expired on {$expiresAt}. Full branch access is locked. Please clear your subscription fee ({$fee}) to resume operations.";
                } elseif ($overdueAction === 'read_only') {
                    $computedStatus = 'read_only';
                    $isActive = true;
                    $isSuspended = false;
                    $warningMessage = "Your branch subscription grace period expired on {$expiresAt}. Read-only mode is active. Sales and record updates are disabled.";
                } elseif ($overdueAction === 'restrict_sales') {
                    $computedStatus = 'sales_restricted';
                    $isActive = true;
                    $isSuspended = false;
                    $warningMessage = "Your branch subscription grace period expired on {$expiresAt}. POS & Sales checkout is disabled until renewal.";
                } else {
                    $computedStatus = 'overdue_unrestricted';
                    $isActive = true;
                    $isSuspended = false;
                    $warningMessage = "Your branch subscription is overdue since {$expiresAt}. Please clear your subscription fee ({$fee}).";
                }
            }
        }

        $cycle = $plan;
        $cycleDays = match ($cycle) {
            'monthly' => 30,
            'quarterly' => 90,
            'half_yearly' => 180,
            'yearly' => 365,
            'trial' => 14,
            'lifetime' => null,
            'custom_days' => $branch->custom_cycle_days !== null && $branch->custom_cycle_days > 0 ? (int) $branch->custom_cycle_days : 30,
            default => BusinessSettings::getInt('subscription_billing_cycle_days', 30),
        };

        $isSalesRestricted = $isSuspended || (! $isInGracePeriod && $isOverdue && in_array($overdueAction, ['restrict_sales', 'read_only', 'suspend_branch'], true));
        $isReadOnly = $isSuspended || (! $isInGracePeriod && $isOverdue && in_array($overdueAction, ['read_only', 'suspend_branch'], true));

        $pendingBillsCount = ($isOverdue && $cycleDays > 0) ? (int) max(1, (int) ceil($overdueDays / $cycleDays)) : 0;
        $totalOverdueFee = $isOverdue ? ($pendingBillsCount * $fee) : 0.0;

        $planLabel = match ($plan) {
            'custom_days' => $cycleDays ? "Custom ({$cycleDays} Days)" : 'Custom Days',
            'monthly' => 'Monthly (30 Days)',
            'quarterly' => 'Quarterly (90 Days)',
            'half_yearly' => 'Half-Yearly (180 Days)',
            'yearly' => 'Yearly (365 Days)',
            'trial' => 'Trial (14 Days)',
            'lifetime' => 'Lifetime',
            default => $cycleDays ? ucfirst($plan)." ({$cycleDays} Days)" : ucfirst($plan),
        };

        $pendingPayment = BranchSubscriptionPayment::where('branch_id', $branch->id)
            ->where('status', 'pending')
            ->latest('id')
            ->first();

        return [
            'branch_id' => $branch->id,
            'branch_name' => $branch->name,
            'is_main_branch' => false,
            'plan' => $plan,
            'plan_label' => $planLabel,
            'cycle_days' => $cycleDays,
            'status' => $rawStatus,
            'computed_status' => $computedStatus,
            'fee' => $fee,
            'total_overdue_fee' => $totalOverdueFee,
            'pending_bills_count' => $pendingBillsCount,
            'custom_fee' => $branch->subscription_fee,
            'starts_at' => $startsAt,
            'expires_at' => $expiresAt,
            'last_paid_at' => $lastPaidAt,
            'days_remaining' => $daysRemaining,
            'overdue_days' => $overdueDays,
            'grace_days_remaining' => $graceDaysRemaining,
            'is_active' => $isActive,
            'is_expiring_soon' => $isExpiringSoon,
            'is_overdue' => $isOverdue,
            'is_in_grace_period' => $isInGracePeriod,
            'is_suspended' => $isSuspended,
            'is_sales_restricted' => $isSalesRestricted,
            'is_read_only' => $isReadOnly,
            'has_pending_payment' => $pendingPayment !== null,
            'pending_payment' => $pendingPayment ? [
                'id' => $pendingPayment->id,
                'amount' => (float) $pendingPayment->amount,
                'payment_method' => $pendingPayment->payment_method,
                'transaction_reference' => $pendingPayment->transaction_reference,
                'paid_at' => $pendingPayment->paid_at ? Carbon::parse($pendingPayment->paid_at)->format('Y-m-d') : null,
                'attachment_path' => $pendingPayment->attachment_path,
                'attachment_url' => $pendingPayment->attachment_url,
                'notes' => $pendingPayment->notes,
                'created_at' => $pendingPayment->created_at?->diffForHumans(),
            ] : null,
            'warning_days' => $warningDays,
            'grace_period_days' => $gracePeriodDays,
            'custom_cycle_days' => $branch->custom_cycle_days,
            'custom_warning_days' => $branch->custom_warning_days,
            'custom_grace_period_days' => $branch->custom_grace_period_days,
            'custom_overdue_action' => $branch->custom_overdue_action,
            'subscription_notes' => $branch->subscription_notes,
            'overdue_action' => $overdueAction,
            'warning_message' => $warningMessage,
            'payment_instructions' => BusinessSettings::get('subscription_payment_instructions', ''),
            'superadmin_contact' => [
                'name' => BusinessSettings::get('superadmin_contact_name'),
                'phone' => BusinessSettings::get('superadmin_contact_phone'),
                'email' => BusinessSettings::get('superadmin_contact_email'),
            ],
        ];
    }

    public function provisionDefaultSubscription(Branch $branch): void
    {
        if (Branch::isMainBranch($branch->id)) {
            $branch->update([
                'subscription_plan' => 'enterprise',
                'subscription_status' => 'lifetime',
                'subscription_fee' => 0,
                'subscription_starts_at' => $branch->subscription_starts_at ?? now()->toDateString(),
            ]);

            return;
        }

        if ($branch->subscription_expires_at !== null) {
            return;
        }

        $cycleDays = BusinessSettings::getInt('subscription_billing_cycle_days', 30);
        $defaultFee = BusinessSettings::getFloat('subscription_default_fee', 1500);

        $branch->update([
            'subscription_plan' => $branch->subscription_plan ?: 'standard',
            'subscription_status' => $branch->subscription_status ?: 'active',
            'subscription_fee' => $branch->subscription_fee !== null ? $branch->subscription_fee : $defaultFee,
            'subscription_starts_at' => $branch->subscription_starts_at ?? now()->toDateString(),
            'subscription_expires_at' => now()->addDays($cycleDays)->toDateString(),
            'subscription_last_paid_at' => $branch->subscription_last_paid_at ?? now()->toDateString(),
        ]);

        $this->syncCycleAccrual($branch);
    }

    /**
     * Client branch submits payment details and screenshot receipt for review.
     * Does NOT renew or extend subscription until SuperAdmin approves and confirms.
     *
     * @param  array<string, mixed>  $data
     */
    public function submitPayment(Branch $branch, array $data, ?User $submittedBy = null): BranchSubscriptionPayment
    {
        $durationDays = (int) ($data['duration_days'] ?? 30);
        $amount = (float) ($data['amount'] ?? ($branch->subscription_fee ?? BusinessSettings::getFloat('subscription_default_fee', 1500)));
        $paymentMethod = $data['payment_method'] ?? 'bkash';
        $transactionReference = $data['transaction_reference'] ?? null;
        $notes = $data['notes'] ?? null;
        $paidAt = $data['paid_at'] ?? now()->toDateString();
        $attachmentPath = null;

        if (isset($data['attachment']) && $data['attachment'] instanceof UploadedFile) {
            $attachmentPath = $data['attachment']->store('subscription-receipts', 'public');
        } elseif (isset($data['attachment_path']) && is_string($data['attachment_path'])) {
            $attachmentPath = $data['attachment_path'];
        }

        $baseDate = $branch->subscription_expires_at
            ? Carbon::parse($branch->subscription_expires_at)->startOfDay()
            : ($branch->subscription_starts_at ? Carbon::parse($branch->subscription_starts_at)->startOfDay() : Carbon::today());

        $periodStartsAt = $baseDate->copy()->toDateString();
        $newExpiryDate = $baseDate->copy()->addDays($durationDays)->toDateString();

        return BranchSubscriptionPayment::create([
            'branch_id' => $branch->id,
            'amount' => $amount,
            'payment_method' => $paymentMethod,
            'payment_account_id' => isset($data['payment_account_id']) ? (int) $data['payment_account_id'] : null,
            'status' => 'pending',
            'transaction_reference' => $transactionReference,
            'billing_period_starts_at' => $periodStartsAt,
            'billing_period_ends_at' => $newExpiryDate,
            'paid_at' => $paidAt,
            'recorded_by_user_id' => $submittedBy?->id,
            'notes' => $notes,
            'attachment_path' => $attachmentPath,
        ]);
    }

    /**
     * SuperAdmin confirms payment and renews the branch subscription.
     *
     * @param  array<string, mixed>  $data
     */
    public function renew(Branch $branch, array $data, ?User $recordedBy = null): BranchSubscriptionPayment
    {
        $durationDays = (int) ($data['duration_days'] ?? 30);
        $amount = (float) ($data['amount'] ?? ($branch->subscription_fee ?? BusinessSettings::getFloat('subscription_default_fee', 1500)));
        $paymentMethod = $data['payment_method'] ?? 'cash';
        $paymentAccountId = isset($data['payment_account_id']) ? (int) $data['payment_account_id'] : null;
        $transactionReference = $data['transaction_reference'] ?? null;
        $notes = $data['notes'] ?? null;
        $paidAt = $data['paid_at'] ?? now()->toDateString();
        $attachmentPath = null;

        if (isset($data['attachment']) && $data['attachment'] instanceof UploadedFile) {
            $attachmentPath = $data['attachment']->store('subscription-receipts', 'public');
        } elseif (! empty($data['existing_attachment_path']) && is_string($data['existing_attachment_path'])) {
            $attachmentPath = $data['existing_attachment_path'];
        } elseif (isset($data['attachment_path']) && is_string($data['attachment_path'])) {
            $attachmentPath = $data['attachment_path'];
        }

        $pendingPaymentId = $data['pending_payment_id'] ?? null;
        $pendingPayment = null;
        if ($pendingPaymentId) {
            $pendingPayment = BranchSubscriptionPayment::where('branch_id', $branch->id)
                ->where('id', $pendingPaymentId)
                ->first();
        } else {
            $pendingPayment = BranchSubscriptionPayment::where('branch_id', $branch->id)
                ->where('status', 'pending')
                ->latest('id')
                ->first();
        }

        // Continuous extension from current expiration date so unpaid overdue cycles are strictly preserved
        $baseDate = $branch->subscription_expires_at
            ? Carbon::parse($branch->subscription_expires_at)->startOfDay()
            : ($branch->subscription_starts_at ? Carbon::parse($branch->subscription_starts_at)->startOfDay() : Carbon::today());

        $periodStartsAt = $baseDate->copy()->toDateString();
        $newExpiryDate = $baseDate->copy()->addDays($durationDays)->toDateString();

        $branch->update([
            'subscription_status' => 'active',
            'subscription_expires_at' => $newExpiryDate,
            'subscription_last_paid_at' => $paidAt,
        ]);

        if ($pendingPayment) {
            $pendingPayment->update([
                'amount' => $amount,
                'payment_method' => $paymentMethod ?: $pendingPayment->payment_method,
                'payment_account_id' => $paymentAccountId ?: $pendingPayment->payment_account_id,
                'status' => 'approved',
                'transaction_reference' => $transactionReference ?: $pendingPayment->transaction_reference,
                'billing_period_starts_at' => $periodStartsAt,
                'billing_period_ends_at' => $newExpiryDate,
                'paid_at' => $paidAt,
                'recorded_by_user_id' => $recordedBy?->id ?? $pendingPayment->recorded_by_user_id,
                'notes' => $notes ?: $pendingPayment->notes,
                'attachment_path' => $attachmentPath ?: $pendingPayment->attachment_path,
            ]);

            $this->accounting->recordPaymentSettlement($pendingPayment->fresh(), $recordedBy);

            return $pendingPayment;
        }

        $payment = BranchSubscriptionPayment::create([
            'branch_id' => $branch->id,
            'amount' => $amount,
            'payment_method' => $paymentMethod,
            'payment_account_id' => $paymentAccountId,
            'status' => 'approved',
            'transaction_reference' => $transactionReference,
            'billing_period_starts_at' => $periodStartsAt,
            'billing_period_ends_at' => $newExpiryDate,
            'paid_at' => $paidAt,
            'recorded_by_user_id' => $recordedBy?->id,
            'notes' => $notes,
            'attachment_path' => $attachmentPath,
        ]);

        $this->accounting->recordPaymentSettlement($payment, $recordedBy);

        return $payment;
    }

    public function syncCycleAccrual(Branch $branch): void
    {
        if (Branch::isMainBranch($branch->id)) {
            return;
        }

        $fee = (float) ($branch->subscription_fee ?? BusinessSettings::getFloat('subscription_default_fee', 1500));
        if ($fee <= 0) {
            return;
        }

        $startsAt = $branch->subscription_starts_at ? Carbon::parse($branch->subscription_starts_at)->toDateString() : now()->toDateString();
        $expiresAt = $branch->subscription_expires_at ? Carbon::parse($branch->subscription_expires_at)->toDateString() : now()->toDateString();

        $this->accounting->recordCycleAccrual($branch, $startsAt, $expiresAt, $fee);
    }

    protected function formatWarningTemplate(
        string $template,
        string $branchName,
        int $daysLeft,
        ?string $dueDate,
        float $feeAmount
    ): string {
        if (empty(trim($template))) {
            return "Your subscription will expire in {$daysLeft} days (Due: {$dueDate}). Please pay {$feeAmount} to avoid service disruption.";
        }

        return str_replace(
            ['{branch_name}', '{days_left}', '{due_date}', '{fee_amount}'],
            [$branchName, (string) $daysLeft, (string) $dueDate, number_format($feeAmount, 2)],
            $template
        );
    }

    protected function formatOverdueActionLabel(string $action): string
    {
        return match ($action) {
            'restrict_sales' => 'POS & sales checkout will be disabled',
            'read_only' => 'read-only mode will be enforced',
            'suspend_branch' => 'branch access will be suspended',
            default => 'service restriction begins',
        };
    }
}
