<?php

namespace App\Services;

use App\Enums\BusinessSessionOpeningMethod;
use App\Enums\BusinessSessionStatus;
use App\Models\Branch;
use App\Models\BusinessSession;
use App\Models\BusinessSessionAccountBalance;
use App\Models\BusinessSessionAuditLog;
use App\Models\ChartOfAccount;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BusinessSessionService
{
    public function __construct(private BusinessSessionReportService $reports) {}

    public function resolveBranchIdForUser(User $user): ?int
    {
        return $user->branch_id;
    }

    public function activeSessionForUser(?User $user = null): ?BusinessSession
    {
        $user ??= Auth::user();

        if (! $user instanceof User) {
            return null;
        }

        return BusinessSession::query()
            ->where('started_by_user_id', $user->id)
            ->whereIn('status', [
                BusinessSessionStatus::Open,
                BusinessSessionStatus::Reopened,
                BusinessSessionStatus::ClosingPending,
            ])
            ->latest('started_at')
            ->first();
    }

    public function activeSessionIdForUser(?User $user = null): ?int
    {
        return $this->activeSessionForUser($user)?->id;
    }

    /**
     * @return array<string, mixed>
     */
    public function sharedPanelState(?User $user = null): array
    {
        $user ??= Auth::user();

        if (! $user instanceof User) {
            return [
                'active' => false,
                'can_start' => false,
                'can_close' => false,
                'can_resume_close' => false,
            ];
        }

        $session = $this->activeSessionForUser($user);
        $canStart = $user->can('business-session.start') && $session === null;
        $isClosingPending = $session?->status === BusinessSessionStatus::ClosingPending;
        $canClose = $user->can('business-session.close') && $session !== null && ! $isClosingPending;
        $canResumeClose = $user->can('business-session.close') && $isClosingPending;

        if ($session === null) {
            return [
                'active' => false,
                'can_start' => $canStart,
                'can_close' => false,
                'can_resume_close' => false,
            ];
        }

        return [
            'active' => true,
            'id' => $session->id,
            'session_number' => $session->session_number,
            'status' => $session->status->label(),
            'started_at' => $session->started_at->toIso8601String(),
            'started_at_time' => $session->started_at->timezone(config('app.timezone'))->format('g:i A'),
            'can_start' => false,
            'can_close' => $canClose,
            'can_resume_close' => $canResumeClose,
        ];
    }

    public function start(User $user, BusinessSessionOpeningMethod $method): BusinessSession
    {
        $this->authorizeStart($user);

        $branchId = $this->resolveBranchIdForUser($user);

        if ($this->activeSessionForUser($user) !== null) {
            throw ValidationException::withMessages([
                'session' => 'An active business session already exists for this user.',
            ]);
        }

        return DB::transaction(function () use ($user, $method, $branchId) {
            $now = now();
            $accounts = $this->accountsForBranch($branchId);
            $totalOpening = round($accounts->sum(fn (ChartOfAccount $account) => (float) $account->current_balance), 2);

            $session = BusinessSession::query()->create([
                'session_number' => $this->generateSessionNumber($branchId, $user->id),
                'session_date' => $now->toDateString(),
                'branch_id' => $branchId,
                'started_by_user_id' => $user->id,
                'started_at' => $now,
                'opening_method' => $method,
                'status' => BusinessSessionStatus::Open,
                'total_opening_balance' => $totalOpening,
            ]);

            foreach ($accounts as $account) {
                BusinessSessionAccountBalance::query()->create([
                    'business_session_id' => $session->id,
                    'account_id' => $account->id,
                    'account_name' => $account->name,
                    'account_type' => $account->type,
                    'opening_balance' => $account->current_balance,
                ]);
            }

            $this->log($session, $user, 'session.started', [
                'opening_method' => $method->label(),
                'account_count' => $accounts->count(),
                'total_opening_balance' => $totalOpening,
            ]);

            $releasedCount = app(BusinessSessionTransactionScope::class)->syncSessionTransactions($session);

            if ($releasedCount > 0) {
                $this->log($session, $user, 'session.released_pre_session_transactions', [
                    'count' => $releasedCount,
                ]);
            }

            return $session->fresh(['startedBy', 'branch']);
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function resolveReport(BusinessSession $session): array
    {
        if (
            $session->status === BusinessSessionStatus::Closed
            && is_array($session->report_snapshot)
            && $session->report_snapshot !== []
        ) {
            return $session->report_snapshot;
        }

        return $this->reports->buildReport($session);
    }

    /**
     * @return array<string, mixed>
     */
    public function buildClosingPreview(BusinessSession $session, User $user): array
    {
        $this->authorizeClose($user, $session);

        if ($session->status === BusinessSessionStatus::Closed) {
            return $this->resolveReport($session);
        }

        if ($session->status !== BusinessSessionStatus::ClosingPending) {
            $session->update(['status' => BusinessSessionStatus::ClosingPending]);
            $this->log($session, $user, 'session.closing_preview', []);
        }

        return $this->reports->buildReport($session);
    }

    public function confirmClose(BusinessSession $session, User $user): BusinessSession
    {
        $this->authorizeClose($user, $session);

        if ($session->status === BusinessSessionStatus::Closed) {
            return $session;
        }

        return DB::transaction(function () use ($session, $user) {
            $report = $this->reports->buildReport($session);
            $now = now();

            $this->reports->persistAccountClosingBalances($session, $report['account_balances']);

            $session->update([
                'status' => BusinessSessionStatus::Closed,
                'closed_at' => $now,
                'closed_by_user_id' => $user->id,
                'total_closing_balance' => $report['closing_summary']['total_closing_balance'],
                'report_snapshot' => $report,
            ]);

            $this->log($session, $user, 'session.closed', [
                'closed_at' => $now->toIso8601String(),
                'total_closing_balance' => $report['closing_summary']['total_closing_balance'],
            ]);

            return $session->fresh(['startedBy', 'closedBy', 'branch']);
        });
    }

    public function cancelClosing(BusinessSession $session, User $user): BusinessSession
    {
        $this->authorizeClose($user, $session);

        if ($session->status === BusinessSessionStatus::ClosingPending) {
            $session->update(['status' => BusinessSessionStatus::Open]);
            $this->log($session, $user, 'session.closing_cancelled', []);
        }

        return $session->fresh();
    }

    public function reopen(BusinessSession $session, User $user): BusinessSession
    {
        if (! $user->can('business-session.reopen')) {
            abort(403);
        }

        if ($session->status !== BusinessSessionStatus::Closed) {
            throw ValidationException::withMessages([
                'session' => 'Only closed sessions can be reopened.',
            ]);
        }

        $hasActiveSession = BusinessSession::query()
            ->where('started_by_user_id', $session->started_by_user_id)
            ->where('id', '!=', $session->id)
            ->whereIn('status', [
                BusinessSessionStatus::Open,
                BusinessSessionStatus::Reopened,
                BusinessSessionStatus::ClosingPending,
            ])
            ->exists();

        if ($hasActiveSession) {
            throw ValidationException::withMessages([
                'session' => 'An active business session already exists for this user.',
            ]);
        }

        $session->update([
            'status' => BusinessSessionStatus::Reopened,
            'closed_at' => null,
            'closed_by_user_id' => null,
        ]);

        $this->log($session, $user, 'session.reopened', []);

        return $session->fresh();
    }

    /**
     * @param  Builder<BusinessSession>  $query
     */
    public function scopeForUser(Builder $query, User $user): Builder
    {
        return $query->where('started_by_user_id', $user->id);
    }

    private function authorizeStart(User $user): void
    {
        if (! $user->can('business-session.start')) {
            abort(403);
        }
    }

    private function authorizeClose(User $user, BusinessSession $session): void
    {
        if (! $user->can('business-session.close')) {
            abort(403);
        }

        if ($session->started_by_user_id !== $user->id) {
            abort(403);
        }
    }

    /**
     * @return Collection<int, ChartOfAccount>
     */
    private function accountsForBranch(?int $branchId)
    {
        $query = ChartOfAccount::query()->whereNotNull('parent_id');

        if ($branchId === null) {
            $query->whereNull('source_type')->whereNull('source_id');
        } else {
            $query->where('source_type', Branch::class)->where('source_id', $branchId);
        }

        return $query->orderBy('code')->get();
    }

    private function generateSessionNumber(?int $branchId, int $userId): string
    {
        $prefix = $branchId === null ? 'HO' : 'BR'.$branchId;
        $datePart = now()->format('Ymd');

        $lastNumber = BusinessSession::query()
            ->where('started_by_user_id', $userId)
            ->whereDate('session_date', now()->toDateString())
            ->count();

        return sprintf('%s-U%d-%s-%03d', $prefix, $userId, $datePart, $lastNumber + 1);
    }

    /**
     * @param  array<string, mixed>  $details
     */
    public function log(?BusinessSession $session, User $user, string $action, array $details = []): void
    {
        BusinessSessionAuditLog::query()->create([
            'business_session_id' => $session?->id,
            'user_id' => $user->id,
            'action' => $action,
            'details' => $details,
        ]);
    }
}
