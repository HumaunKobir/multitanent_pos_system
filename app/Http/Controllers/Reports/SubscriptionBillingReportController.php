<?php

namespace App\Http\Controllers\Reports;

use App\Concerns\ExportsFilteredList;
use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\BranchSubscriptionPayment;
use App\Services\BranchPaymentAccountService;
use App\Services\BranchSubscriptionService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class SubscriptionBillingReportController extends Controller
{
    use ExportsFilteredList;

    public const PERMISSION_VIEW = 'report.subscription-billing.view';

    public function __construct(
        private BranchSubscriptionService $subscriptionService,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize(self::PERMISSION_VIEW);

        $user = Auth::user();
        $isBranchScoped = $user?->usesBranchPanel() ?? false;
        $userBranchId = $user?->branch_id;

        $query = $this->buildQuery($request, $isBranchScoped, $userBranchId);
        $summary = $this->calculateSummary($query);

        $payments = (clone $query)
            ->with([
                'branch:id,name,phone,address,status,subscription_plan,subscription_status,subscription_expires_at',
                'recordedBy:id,name',
            ])
            ->orderByDesc('paid_at')
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString()
            ->through(fn (BranchSubscriptionPayment $payment) => $this->formatPaymentRow($payment));

        // For branch users, also provide their active subscription summary card
        $branchSubscription = null;
        if ($isBranchScoped && $userBranchId) {
            $branch = Branch::query()->find($userBranchId);
            if ($branch) {
                $branchSubscription = $this->subscriptionService->getSubscriptionSummary($branch);
            }
        }

        $branches = ! $isBranchScoped
            ? Branch::query()->where('id', '!=', Branch::MAIN_BRANCH_ID)->orderBy('name')->pluck('name', 'id')
            : [];

        $paymentMethods = BranchPaymentAccountService::listForBranch($isBranchScoped ? $userBranchId : null)
            ->pluck('name', 'name')
            ->toArray();

        if (empty($paymentMethods)) {
            $paymentMethods = [
                'Cash in Hand' => 'Cash in Hand',
                'bKash' => 'bKash',
                'Nagad' => 'Nagad',
                'Bank Transfer' => 'Bank Transfer',
                'Online Payment' => 'Online Payment',
            ];
        }

        return Inertia::render('admin/reports/subscription-billing', [
            'payments' => $payments,
            'summary' => $summary,
            'branchSubscription' => $branchSubscription,
            'isBranchScoped' => $isBranchScoped,
            'filters' => [
                'search' => $request->input('search'),
                'branch_id' => ! $isBranchScoped ? $request->input('branch_id') : null,
                'status' => $request->input('status', 'all'),
                'payment_method' => $request->input('payment_method', 'all'),
                'date_from' => $request->input('date_from'),
                'date_to' => $request->input('date_to'),
            ],
            'branches' => $branches,
            'paymentMethods' => $paymentMethods,
        ]);
    }

    protected function buildQuery(Request $request, bool $isBranchScoped, ?int $userBranchId): Builder
    {
        $query = BranchSubscriptionPayment::query();

        if ($isBranchScoped && $userBranchId) {
            $query->where('branch_id', $userBranchId);
        } elseif ($request->filled('branch_id') && (int) $request->input('branch_id') > 0) {
            $query->where('branch_id', (int) $request->input('branch_id'));
        }

        if ($request->filled('status') && $request->input('status') !== 'all') {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('payment_method') && $request->input('payment_method') !== 'all') {
            $query->where('payment_method', $request->input('payment_method'));
        }

        if ($request->filled('date_from')) {
            $query->whereDate('paid_at', '>=', $request->input('date_from'));
        }

        if ($request->filled('date_to')) {
            $query->whereDate('paid_at', '<=', $request->input('date_to'));
        }

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function (Builder $q) use ($search) {
                $q->where('transaction_reference', 'like', "%{$search}%")
                    ->orWhere('notes', 'like', "%{$search}%")
                    ->orWhereHas('branch', function (Builder $bq) use ($search) {
                        $bq->where('name', 'like', "%{$search}%")
                            ->orWhere('phone', 'like', "%{$search}%");
                    });
            });
        }

        return $query;
    }

    /**
     * @return array<string, mixed>
     */
    protected function calculateSummary(Builder $query): array
    {
        $all = (clone $query)->get(['amount', 'status']);

        $totalApproved = $all->where('status', 'approved')->sum('amount');
        $totalPending = $all->where('status', 'pending')->sum('amount');
        $totalRejected = $all->where('status', 'rejected')->sum('amount');
        $totalAmount = $all->sum('amount');

        return [
            'total_count' => $all->count(),
            'approved_count' => $all->where('status', 'approved')->count(),
            'pending_count' => $all->where('status', 'pending')->count(),
            'rejected_count' => $all->where('status', 'rejected')->count(),
            'total_collected' => round((float) $totalApproved, 2),
            'pending_amount' => round((float) $totalPending, 2),
            'rejected_amount' => round((float) $totalRejected, 2),
            'total_amount' => round((float) $totalAmount, 2),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function formatPaymentRow(BranchSubscriptionPayment $payment): array
    {
        return [
            'id' => $payment->id,
            'branch_id' => $payment->branch_id,
            'branch_name' => $payment->branch?->name ?? '—',
            'branch_phone' => $payment->branch?->phone ?? '—',
            'amount' => (float) $payment->amount,
            'payment_method' => $payment->payment_method ?? '—',
            'status' => $payment->status ?? 'approved',
            'transaction_reference' => $payment->transaction_reference ?? '—',
            'billing_period_starts_at' => $payment->billing_period_starts_at?->format('Y-m-d'),
            'billing_period_ends_at' => $payment->billing_period_ends_at?->format('Y-m-d'),
            'paid_at' => $payment->paid_at?->format('Y-m-d'),
            'recorded_by' => $payment->recordedBy?->name ?? 'Online / Client',
            'notes' => $payment->notes,
            'attachment_path' => $payment->attachment_path,
            'attachment_url' => $payment->attachment_url,
            'created_at' => $payment->created_at?->format('Y-m-d H:i'),
        ];
    }

    /**
     * @return Collection<int, list<string|int|float|null>>
     */
    protected function exportRows(Request $request): Collection
    {
        $user = Auth::user();
        $isBranchScoped = $user?->usesBranchPanel() ?? false;
        $userBranchId = $user?->branch_id;

        $payments = $this->buildQuery($request, $isBranchScoped, $userBranchId)
            ->with(['branch:id,name,phone', 'recordedBy:id,name'])
            ->orderByDesc('paid_at')
            ->orderByDesc('id')
            ->limit(self::LIST_EXPORT_LIMIT)
            ->get();

        return $payments->values()->map(function (BranchSubscriptionPayment $payment, int $index): array {
            return [
                $index + 1,
                $payment->paid_at?->format('Y-m-d') ?? '—',
                $payment->branch?->name ?? '—',
                $payment->billing_period_starts_at?->format('Y-m-d') ?? '—',
                $payment->billing_period_ends_at?->format('Y-m-d') ?? '—',
                (float) $payment->amount,
                $payment->payment_method ?? '—',
                $payment->transaction_reference ?? '—',
                ucfirst($payment->status ?? 'approved'),
                $payment->recordedBy?->name ?? 'Online / Client',
                $payment->notes ?? '—',
            ];
        });
    }

    public function exportExcel(Request $request): BinaryFileResponse
    {
        $this->authorize(self::PERMISSION_VIEW);

        return $this->downloadListExcel(
            'subscription-billing-report',
            ['#', 'Paid Date', 'Branch', 'Billing Start', 'Billing End', 'Amount', 'Payment Method', 'Transaction Ref', 'Status', 'Recorded By', 'Notes'],
            $this->exportRows($request),
        );
    }

    public function exportCsv(Request $request): SymfonyResponse
    {
        $this->authorize(self::PERMISSION_VIEW);

        return $this->downloadListCsv(
            'subscription-billing-report',
            ['#', 'Paid Date', 'Branch', 'Billing Start', 'Billing End', 'Amount', 'Payment Method', 'Transaction Ref', 'Status', 'Recorded By', 'Notes'],
            $this->exportRows($request),
        );
    }

    public function exportPdf(Request $request): SymfonyResponse
    {
        $this->authorize(self::PERMISSION_VIEW);

        return $this->downloadListPdf(
            'Subscription Billing Report',
            ['#', 'Paid Date', 'Branch', 'Billing Start', 'Billing End', 'Amount', 'Payment Method', 'Transaction Ref', 'Status', 'Recorded By', 'Notes'],
            $this->exportRows($request),
        );
    }

    public function exportPrint(Request $request): SymfonyResponse
    {
        $this->authorize(self::PERMISSION_VIEW);

        return $this->printListHtml(
            'Subscription Billing Report',
            ['#', 'Paid Date', 'Branch', 'Billing Start', 'Billing End', 'Amount', 'Payment Method', 'Transaction Ref', 'Status', 'Recorded By', 'Notes'],
            $this->exportRows($request),
        );
    }
}
