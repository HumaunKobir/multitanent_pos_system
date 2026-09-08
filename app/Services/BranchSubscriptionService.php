<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\BranchSubscriptionPayment;
use App\Models\User;
use App\Support\BusinessSettings;
use Illuminate\Support\Carbon;

class BranchSubscriptionService
{
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
            default => BusinessSettings::getInt('subscription_billing_cycle_days', 30),
        };

        if ($cycle === 'custom_days' && $startsAt && $expiresAt) {
            $diff = (int) Carbon::parse($startsAt)->diffInDays(Carbon::parse($expiresAt));
            if ($diff > 0) {
                $cycleDays = $diff;
            }
        }

        $isSalesRestricted = $isSuspended || (! $isInGracePeriod && $isOverdue && in_array($overdueAction, ['restrict_sales', 'read_only', 'suspend_branch'], true));
        $isReadOnly = $isSuspended || (! $isInGracePeriod && $isOverdue && in_array($overdueAction, ['read_only', 'suspend_branch'], true));

        return [
            'branch_id' => $branch->id,
            'branch_name' => $branch->name,
            'is_main_branch' => false,
            'plan' => $plan,
            'cycle_days' => $cycleDays,
            'status' => $rawStatus,
            'computed_status' => $computedStatus,
            'fee' => $fee,
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
            'warning_days' => $warningDays,
            'grace_period_days' => $gracePeriodDays,
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
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function renew(Branch $branch, array $data, ?User $recordedBy = null): BranchSubscriptionPayment
    {
        $durationDays = (int) ($data['duration_days'] ?? 30);
        $amount = (float) ($data['amount'] ?? ($branch->subscription_fee ?? BusinessSettings::getFloat('subscription_default_fee', 1500)));
        $paymentMethod = $data['payment_method'] ?? 'cash';
        $transactionReference = $data['transaction_reference'] ?? null;
        $notes = $data['notes'] ?? null;
        $paidAt = $data['paid_at'] ?? now()->toDateString();

        // Calculate new period
        $currentExpiry = $branch->subscription_expires_at ? Carbon::parse($branch->subscription_expires_at) : Carbon::today();
        $baseDate = $currentExpiry->isFuture() ? $currentExpiry : Carbon::today();

        $periodStartsAt = $baseDate->copy()->toDateString();
        $newExpiryDate = $baseDate->copy()->addDays($durationDays)->toDateString();

        $branch->update([
            'subscription_status' => 'active',
            'subscription_expires_at' => $newExpiryDate,
            'subscription_last_paid_at' => $paidAt,
        ]);

        return BranchSubscriptionPayment::create([
            'branch_id' => $branch->id,
            'amount' => $amount,
            'payment_method' => $paymentMethod,
            'transaction_reference' => $transactionReference,
            'billing_period_starts_at' => $periodStartsAt,
            'billing_period_ends_at' => $newExpiryDate,
            'paid_at' => $paidAt,
            'recorded_by_user_id' => $recordedBy?->id,
            'notes' => $notes,
        ]);
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

