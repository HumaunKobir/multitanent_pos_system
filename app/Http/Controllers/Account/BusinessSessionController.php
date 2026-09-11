<?php

namespace App\Http\Controllers\Account;

use App\Enums\BusinessSessionOpeningMethod;
use App\Enums\BusinessSessionStatus;
use App\Exports\BusinessSessionReportExport;
use App\Http\Controllers\Controller;
use App\Models\BusinessSession;
use App\Services\BusinessSessionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class BusinessSessionController extends Controller
{
    public const PERMISSION_VIEW = 'business-session.view';

    public const PERMISSION_START = 'business-session.start';

    public const PERMISSION_CLOSE = 'business-session.close';

    public const PERMISSION_REOPEN = 'business-session.reopen';

    public const PERMISSION_EXPORT = 'business-session.export';

    public function __construct(
        private BusinessSessionService $sessions,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorizeAction($request, self::PERMISSION_VIEW, 'accounts.view');

        $user = $request->user();
        $sessions = BusinessSession::query()
            ->with(['branch:id,name', 'startedBy:id,name', 'closedBy:id,name'])
            ->tap(function ($query) use ($user, $request) {
                if ($request->filled('branch_id') && ! $user?->usesBranchPanel()) {
                    if ($request->input('branch_id') !== 'all') {
                        $query->where('branch_id', (int) $request->input('branch_id'));
                    }

                    return;
                }

                $this->sessions->scopeForUser($query, $user);
            })
            ->when($request->filled('status'), fn ($query) => $query->where('status', (int) $request->input('status')))
            ->when($request->filled('date_from'), fn ($query) => $query->whereDate('session_date', '>=', $request->input('date_from')))
            ->when($request->filled('date_to'), fn ($query) => $query->whereDate('session_date', '<=', $request->input('date_to')))
            ->orderByDesc('started_at')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (BusinessSession $session) => [
                'id' => $session->id,
                'session_number' => $session->session_number,
                'session_date' => $session->session_date->format('Y-m-d'),
                'branch' => $session->branch?->name ?? 'Head Office',
                'started_by' => $session->startedBy?->name ?? '—',
                'started_at' => $session->started_at->toIso8601String(),
                'closed_at' => $session->closed_at?->toIso8601String(),
                'opening_balance' => (float) $session->total_opening_balance,
                'closing_balance' => $session->total_closing_balance !== null ? (float) $session->total_closing_balance : null,
                'status' => $session->status->label(),
                'status_value' => $session->status->value,
                'can_reopen' => $session->status === BusinessSessionStatus::Closed && $user->can(self::PERMISSION_REOPEN),
            ]);

        return Inertia::render('admin/accounts/daily-sessions/index', [
            'sessions' => $sessions,
            'filters' => [
                'status' => $request->input('status', ''),
                'date_from' => $request->input('date_from', ''),
                'date_to' => $request->input('date_to', ''),
            ],
            'statusOptions' => collect(BusinessSessionStatus::cases())->map(fn ($status) => [
                'value' => (string) $status->value,
                'label' => $status->label(),
            ])->values(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeAction($request, self::PERMISSION_START, 'accounts.create');

        $this->sessions->start(
            $request->user(),
            BusinessSessionOpeningMethod::ManualFromPanel,
        );

        return back()->with('success', 'Business session started successfully.');
    }

    public function closePreview(Request $request): JsonResponse|RedirectResponse
    {
        $this->authorizeAction($request, self::PERMISSION_CLOSE, self::PERMISSION_VIEW, 'accounts.view');
        $session = $this->resolveActiveSession($request);

        $report = $this->sessions->buildClosingPreview($session, $request->user());

        $payload = [
            'session' => [
                'id' => $session->id,
                'session_number' => $session->session_number,
            ],
            'report' => $report,
            'can_export' => $request->user()->can(self::PERMISSION_EXPORT),
        ];

        if ($request->wantsJson()) {
            return response()->json($payload);
        }

        return back();
    }

    public function closeConfirm(Request $request): RedirectResponse
    {
        $this->authorizeAction($request, self::PERMISSION_CLOSE);
        $session = $this->resolveActiveSession($request);

        $this->sessions->confirmClose($session, $request->user());

        return redirect()
            ->route('accounts.daily-sessions.index')
            ->with('success', 'Business session closed successfully.');
    }

    public function closeCancel(Request $request): RedirectResponse
    {
        $this->authorizeAction($request, self::PERMISSION_CLOSE);
        $session = $this->resolveActiveSession($request);
        $this->sessions->cancelClosing($session, $request->user());

        return back()->with('success', 'Session closing cancelled.');
    }

    public function report(BusinessSession $dailySession, Request $request): JsonResponse
    {
        $this->authorizeAction($request, self::PERMISSION_VIEW, 'accounts.view');
        $this->authorizeSessionAccess($request, $dailySession);

        $report = $this->sessions->resolveReport($dailySession);

        return response()->json([
            'session' => [
                'id' => $dailySession->id,
                'session_number' => $dailySession->session_number,
            ],
            'report' => $report,
            'can_export' => $request->user()->can(self::PERMISSION_EXPORT) || $request->user()->usesBranchPanel() || $request->user()->hasUnrestrictedPermissions() || $request->user()->isSuperAdmin(),
        ]);
    }

    public function exportExcel(BusinessSession $dailySession, Request $request): BinaryFileResponse
    {
        $this->authorizeAction($request, self::PERMISSION_EXPORT, self::PERMISSION_VIEW, 'accounts.view');
        $this->authorizeSessionAccess($request, $dailySession);

        $report = $this->sessions->resolveReport($dailySession);

        $filename = sprintf('business-session-%s.xlsx', $dailySession->session_number);

        $this->sessions->log($dailySession, $request->user(), 'session.export_excel', []);

        $includeIncomeExpenseSummaries = ! $request->user()->usesBranchPanel();

        return Excel::download(
            new BusinessSessionReportExport($report, $includeIncomeExpenseSummaries),
            $filename,
        );
    }

    public function reopen(BusinessSession $dailySession, Request $request): RedirectResponse
    {
        $this->authorizeAction($request, self::PERMISSION_REOPEN);
        $this->authorizeSessionAccess($request, $dailySession);

        $this->sessions->reopen($dailySession, $request->user());

        return back()->with('success', 'Business session reopened.');
    }

    private function resolveActiveSession(Request $request): BusinessSession
    {
        $session = $this->sessions->activeSessionForUser($request->user());

        if ($session === null) {
            abort(404, 'No active business session found.');
        }

        return $session;
    }

    private function authorizeAction(Request $request, string $permission, string ...$fallbacks): void
    {
        $user = $request->user();

        if ($user === null) {
            abort(401);
        }

        if ($user->hasUnrestrictedPermissions() || $user->isSuperAdmin()) {
            return;
        }

        if ($user->usesBranchPanel()) {
            return;
        }

        if ($user->can($permission)) {
            return;
        }

        foreach ($fallbacks as $fallback) {
            if ($user->can($fallback)) {
                return;
            }
        }

        abort(403);
    }

    private function authorizeSessionAccess(Request $request, BusinessSession $session): void
    {
        $user = $request->user();

        if ($user === null) {
            abort(401);
        }

        if ($user->hasUnrestrictedPermissions() || $user->isSuperAdmin()) {
            return;
        }

        if ((int) $session->started_by_user_id === (int) $user->id) {
            return;
        }

        // Branch panel users can access their branch sessions
        if ($user->usesBranchPanel()) {
            if (config('tenancy.enabled')) {
                return;
            }

            if ($session->branch_id === null || (int) $session->branch_id === (int) $user->branch_id) {
                return;
            }

            abort(403);
        }

        $userBranchId = $user->branch_id;
        $sessionBranchId = $session->branch_id;

        // Main branch / Central admin scope
        if ($userBranchId === null || Branch::isMainBranch($userBranchId)) {
            if ($sessionBranchId !== null && ! Branch::isMainBranch($sessionBranchId)) {
                abort(403);
            }
            return;
        }

        // Branch-scoped user: forbidden only if session explicitly belongs to a different branch
        if ($sessionBranchId !== null && (int) $sessionBranchId !== (int) $userBranchId) {
            abort(403);
        }
    }
}
