<?php

namespace App\Http\Controllers\Reports;

use App\Concerns\ExportsFilteredList;
use App\Http\Controllers\Controller;
use App\Services\BranchSubscriptionService;
use App\Services\PurchaseReportService;
use App\Services\ReportService;
use App\Services\SalesProfitTrendService;
use App\Services\SalesReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class ReportController extends Controller
{
    use ExportsFilteredList;

    public const PERMISSION_CUSTOMER_LEDGER = 'report.customer-ledger.view';

    public const PERMISSION_CASH_FLOW = 'report.cash-flow.view';

    public const PERMISSION_CASH_FLOW_SUMMARY = 'report.cash-flow-summary.view';

    public const PERMISSION_DAILY_TRANSACTIONS = 'report.daily-transactions.view';

    public const PERMISSION_DATE_WISE_STOCK = 'report.date-wise-stock.view';

    public const PERMISSION_DAILY_SUMMARY = 'report.daily-summary.view';

    public const PERMISSION_ACCOUNT_LEDGER = 'report.account-ledger.view';

    public const PERMISSION_ACCOUNT_TRANSACTIONS = 'report.account-transactions.view';

    public const PERMISSION_STOCK_LEDGER = 'report.stock-ledger.view';

    public const PERMISSION_BALANCE_SHEET = 'report.balance-sheet.view';

    public const PERMISSION_SALES_SUMMARY = 'report.sales-summary.view';

    public const PERMISSION_TRIAL_BALANCE = 'report.trial-balance.view';

    public const PERMISSION_PROFIT_LOSS = 'report.profit-loss.view';

    public const PERMISSION_SALES_REPORT = 'report.sales-report.view';

    public const PERMISSION_SALES_PROFIT_TREND = 'report.sales-profit-trend.view';

    public const PERMISSION_PURCHASE_REPORT = 'report.purchase-report.view';

    public function __construct(
        private ReportService $reports,
        private SalesReportService $salesReports,
        private SalesProfitTrendService $salesProfitTrends,
        private PurchaseReportService $purchaseReports,
        private BranchSubscriptionService $subscriptions,
    ) {}

    public function customerLedger(Request $request): Response
    {
        $this->authorize(self::PERMISSION_CUSTOMER_LEDGER);

        $filters = $request->validate([
            'customer_id' => ['nullable', 'integer', 'exists:customers,id'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],
        ]);

        $customerId = isset($filters['customer_id']) ? (int) $filters['customer_id'] : null;
        $data = $customerId
            ? $this->reports->customerLedger($customerId, $filters['date_from'] ?? null, $filters['date_to'] ?? null)
            : ['customer' => null, 'entries' => [], 'totals' => ['debit' => 0, 'credit' => 0, 'balance' => 0]];

        return Inertia::render('admin/reports/customer-ledger', [
            'customers' => $this->reports->customerOptions(),
            'filters' => $filters,
            ...$data,
        ]);
    }

    public function cashFlow(Request $request): Response
    {
        $this->authorize(self::PERMISSION_CASH_FLOW);

        $filters = $request->validate([
            'account_id' => ['nullable', 'integer', 'exists:chart_of_accounts,id'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],
        ]);

        return Inertia::render('admin/reports/cash-flow', [
            'accounts' => $this->reports->cashAccountOptions(),
            'filters' => $filters,
            'entries' => $this->reports->cashFlow(
                isset($filters['account_id']) ? (int) $filters['account_id'] : null,
                $filters['date_from'] ?? null,
                $filters['date_to'] ?? null,
            ),
        ]);
    }

    public function cashFlowSummary(Request $request): Response
    {
        $this->authorize(self::PERMISSION_CASH_FLOW_SUMMARY);

        $filters = $request->validate([
            'account_id' => ['nullable', 'integer', 'exists:chart_of_accounts,id'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],
        ]);

        return Inertia::render('admin/reports/cash-flow-summary', [
            'accounts' => $this->reports->cashAccountOptions(),
            'filters' => $filters,
            'rows' => $this->reports->cashFlowSummary(
                isset($filters['account_id']) ? (int) $filters['account_id'] : null,
                $filters['date_from'] ?? null,
                $filters['date_to'] ?? null,
            ),
        ]);
    }

    public function dailyTransactions(Request $request): Response
    {
        $this->authorize(self::PERMISSION_DAILY_TRANSACTIONS);

        $filters = $request->validate([
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],
        ]);

        if (! isset($filters['date_from']) && ! isset($filters['date_to'])) {
            $filters['date_from'] = now()->format('Y-m-d');
            $filters['date_to'] = now()->format('Y-m-d');
        }

        return Inertia::render('admin/reports/daily-transactions', [
            'filters' => $filters,
            'entries' => $this->reports->dailyTransactions(
                $filters['date_from'] ?? null,
                $filters['date_to'] ?? null,
            ),
        ]);
    }

    public function dateWiseStock(Request $request): Response|JsonResponse
    {
        $this->authorize(self::PERMISSION_DATE_WISE_STOCK);

        $filters = $request->validate([
            'product_id' => ['nullable', 'integer', 'exists:products,id'],
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],
        ]);

        $canFilterByBranch = $this->reports->canFilterByBranch();
        $filterBranchId = $canFilterByBranch && isset($filters['branch_id']) ? (int) $filters['branch_id'] : null;
        $productId = isset($filters['product_id']) ? (int) $filters['product_id'] : null;

        $normalizedFilters = [
            'product_id' => $productId,
            'branch_id' => isset($filters['branch_id']) ? (int) $filters['branch_id'] : null,
            'date_from' => $filters['date_from'] ?? null,
            'date_to' => $filters['date_to'] ?? null,
        ];

        $selectedProduct = $this->reports->selectedProductOption($productId, $filterBranchId);
        $report = $this->reports->dateWiseStock(
            $productId,
            $filters['date_from'] ?? null,
            $filters['date_to'] ?? null,
            $filterBranchId,
        );

        if ($request->expectsJson() && ! $request->header('X-Inertia')) {
            return response()->json([
                'mode' => $report['mode'],
                'opening_stock' => $report['opening_stock'],
                'totals' => $report['totals'],
                'entries' => $report['entries'],
                'filters' => $normalizedFilters,
                'selected_product' => $selectedProduct,
            ]);
        }

        return Inertia::render('admin/reports/date-wise-stock', [
            'branches' => $canFilterByBranch ? $this->reports->branchOptions() : [],
            'isBranchScoped' => ! $canFilterByBranch,
            'selected_product' => $selectedProduct,
            'filters' => $normalizedFilters,
            'mode' => $report['mode'],
            'opening_stock' => $report['opening_stock'],
            'totals' => $report['totals'],
            'entries' => $report['entries'],
        ]);
    }

    public function dailySummary(Request $request): Response
    {
        $this->authorize(self::PERMISSION_DAILY_SUMMARY);

        $filters = $request->validate([
            'date' => ['nullable', 'date'],
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
        ]);

        $date = $filters['date'] ?? now()->format('Y-m-d');
        $userBranchId = Auth::user()?->branch_id;
        $canFilterByBranch = $this->reports->canFilterByBranch();
        $filterBranchId = $canFilterByBranch && isset($filters['branch_id']) ? (int) $filters['branch_id'] : null;
        $filterUserId = isset($filters['user_id']) ? (int) $filters['user_id'] : null;

        return Inertia::render('admin/reports/daily-summary', [
            'filters' => [
                'date' => $date,
                'branch_id' => $filters['branch_id'] ?? null,
                'user_id' => $filters['user_id'] ?? null,
            ],
            'branches' => $canFilterByBranch ? $this->reports->branchOptions() : [],
            'users' => $this->reports->userOptions($filterBranchId ?? ($canFilterByBranch ? null : $userBranchId)),
            'isBranchScoped' => ! $canFilterByBranch,
            'summary' => $this->reports->dailySummary($date, $filterBranchId, $filterUserId),
        ]);
    }

    public function accountLedger(Request $request): Response
    {
        $this->authorize(self::PERMISSION_ACCOUNT_LEDGER);

        $filters = $request->validate([
            'account_id' => ['nullable', 'integer', 'exists:chart_of_accounts,id'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],
        ]);

        $accountId = isset($filters['account_id']) ? (int) $filters['account_id'] : null;
        $data = $accountId
            ? $this->reports->accountLedger($accountId, $filters['date_from'] ?? null, $filters['date_to'] ?? null)
            : ['account' => null, 'opening_balance' => 0, 'entries' => [], 'totals' => ['debit' => 0, 'credit' => 0, 'balance' => 0]];

        return Inertia::render('admin/reports/account-ledger', [
            'accounts' => $this->reports->accountOptions(),
            'filters' => $filters,
            ...$data,
        ]);
    }

    public function accountTransactions(Request $request): Response
    {
        $this->authorize(self::PERMISSION_ACCOUNT_TRANSACTIONS);

        $filters = $request->validate([
            'account_id' => ['nullable', 'integer', 'exists:chart_of_accounts,id'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],
        ]);

        if (! isset($filters['date_from']) && ! isset($filters['date_to'])) {
            $filters['date_from'] = now()->startOfMonth()->format('Y-m-d');
            $filters['date_to'] = now()->format('Y-m-d');
        }

        return Inertia::render('admin/reports/account-transactions', [
            'accounts' => $this->reports->accountOptions(),
            'filters' => $filters,
            'transactions' => $this->reports->accountTransactions(
                isset($filters['account_id']) ? (int) $filters['account_id'] : null,
                $filters['date_from'] ?? null,
                $filters['date_to'] ?? null,
            ),
        ]);
    }

    public function stockLedger(Request $request): Response
    {
        $this->authorize(self::PERMISSION_STOCK_LEDGER);

        $filters = $request->validate([
            'product_id' => ['nullable', 'integer', 'exists:products,id'],
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],
        ]);

        $userBranchId = Auth::user()?->branch_id;
        $branchId = $userBranchId ?? (isset($filters['branch_id']) ? (int) $filters['branch_id'] : null);
        $productId = isset($filters['product_id']) ? (int) $filters['product_id'] : null;

        return Inertia::render('admin/reports/stock-ledger', [
            'products' => $this->reports->productOptions(),
            'branches' => $userBranchId === null ? $this->reports->branchOptions() : [],
            'isBranchScoped' => $userBranchId !== null,
            'filters' => $filters,
            ...$this->reports->stockLedger(
                $productId,
                $branchId,
                $filters['date_from'] ?? null,
                $filters['date_to'] ?? null,
            ),
        ]);
    }

    public function salesSummary(Request $request): Response
    {
        $this->authorize(self::PERMISSION_SALES_SUMMARY);

        $filters = $request->validate([
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],
            'product_id' => ['nullable', 'integer', 'exists:products,id'],
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
            'sort' => ['nullable', 'in:desc,asc'],
            'discount' => ['nullable', 'string', 'max:100'],
        ]);

        if (! isset($filters['date_from']) && ! isset($filters['date_to'])) {
            $filters['date_from'] = now()->startOfMonth()->format('Y-m-d');
            $filters['date_to'] = now()->format('Y-m-d');
        }

        $canFilterByBranch = $this->reports->canFilterByBranch();
        $filterBranchId = $canFilterByBranch && isset($filters['branch_id']) ? (int) $filters['branch_id'] : null;
        $productId = isset($filters['product_id']) ? (int) $filters['product_id'] : null;
        $sort = $filters['sort'] ?? 'desc';
        $discountFilter = isset($filters['discount']) && $filters['discount'] !== 'all'
            ? $filters['discount']
            : null;
        $dateFrom = $filters['date_from'] ?? null;
        $dateTo = $filters['date_to'] ?? null;

        $report = $this->reports->salesSummary(
            $dateFrom,
            $dateTo,
            $productId,
            $filterBranchId,
            $sort,
            $discountFilter,
        );

        return Inertia::render('admin/reports/sales-summary', [
            'branches' => $canFilterByBranch ? $this->reports->branchOptions() : [],
            'discounts' => $this->reports->salesSummaryDiscountOptions($dateFrom, $dateTo, $filterBranchId),
            'isBranchScoped' => ! $canFilterByBranch,
            'selected_product' => $this->reports->selectedProductOption($productId, $filterBranchId),
            'filters' => [
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
                'product_id' => isset($filters['product_id']) ? (int) $filters['product_id'] : null,
                'branch_id' => isset($filters['branch_id']) ? (int) $filters['branch_id'] : null,
                'sort' => $sort,
                'discount' => $filters['discount'] ?? 'all',
            ],
            'rows' => $report['rows'],
            'discount_summary' => $report['discount_summary'],
            'top_discount' => $report['top_discount'],
        ]);
    }

    public function searchProducts(Request $request): JsonResponse
    {
        $this->authorizeReportProductSearch();

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'selected_id' => ['nullable', 'integer', 'exists:products,id'],
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
        ]);

        $canFilterByBranch = $this->reports->canFilterByBranch();
        $filterBranchId = $canFilterByBranch && isset($filters['branch_id']) ? (int) $filters['branch_id'] : null;

        return response()->json(
            $this->reports->searchProductOptions(
                $filters['search'] ?? null,
                isset($filters['selected_id']) ? (int) $filters['selected_id'] : null,
                $filterBranchId,
            ),
        );
    }

    private function syncOverdueSubscriptions(Request $request): void
    {
        $user = $request->user();

        if ($user === null) {
            return;
        }

        try {
            if ($user->usesBranchPanel() && $user->branch !== null) {
                $this->subscriptions->catchUpBranchBilling($user->branch);

                return;
            }

            if ($user->usesAdminPanel() || $user->bypassesPermissionChecks()) {
                $this->subscriptions->syncAllOverdueLiabilities();
            }
        } catch (\Throwable $e) {
            report($e);
        }
    }

    public function balanceSheet(Request $request): Response
    {
        $this->authorize(self::PERMISSION_BALANCE_SHEET);
        $this->syncOverdueSubscriptions($request);

        $filters = $request->validate([
            'as_of' => ['nullable', 'date'],
        ]);

        $asOf = $filters['as_of'] ?? now()->format('Y-m-d');

        return Inertia::render('admin/reports/balance-sheet', [
            'filters' => ['as_of' => $asOf],
            'sheet' => $this->reports->balanceSheet($asOf),
        ]);
    }

    public function trialBalance(Request $request): Response
    {
        $this->authorize(self::PERMISSION_TRIAL_BALANCE);
        $this->syncOverdueSubscriptions($request);

        $filters = $request->validate([
            'as_of' => ['nullable', 'date'],
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
        ]);

        $asOf = $filters['as_of'] ?? now()->format('Y-m-d');
        $canFilterByBranch = $this->reports->canFilterByBranch();
        $filterBranchId = $canFilterByBranch && isset($filters['branch_id']) ? (int) $filters['branch_id'] : null;

        return Inertia::render('admin/reports/trial-balance', [
            'filters' => [
                'as_of' => $asOf,
                'branch_id' => $filters['branch_id'] ?? null,
            ],
            'branches' => $canFilterByBranch ? $this->reports->branchOptions() : [],
            'isBranchScoped' => ! $canFilterByBranch,
            'report' => $this->reports->trialBalance($asOf, $filterBranchId),
        ]);
    }

    public function profitLoss(Request $request): Response
    {
        $this->authorize(self::PERMISSION_PROFIT_LOSS);
        $this->syncOverdueSubscriptions($request);

        $filters = $request->validate([
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],
        ]);

        if (! isset($filters['date_from']) && ! isset($filters['date_to'])) {
            $filters['date_from'] = now()->startOfMonth()->format('Y-m-d');
            $filters['date_to'] = now()->format('Y-m-d');
        }

        $isSaasPanel = $this->reports->canFilterByBranch();

        return Inertia::render('admin/reports/profit-loss', [
            'filters' => [
                'date_from' => $filters['date_from'] ?? null,
                'date_to' => $filters['date_to'] ?? null,
            ],
            'panelVariant' => $isSaasPanel ? 'saas' : 'branch',
            'isBranchScoped' => ! $isSaasPanel,
            'report' => $this->reports->profitAndLoss(
                $filters['date_from'] ?? null,
                $filters['date_to'] ?? null,
            ),
        ]);
    }

    public function salesProfitTrend(Request $request): Response
    {
        $this->authorize(self::PERMISSION_SALES_PROFIT_TREND);

        $resolved = $this->resolvedSalesProfitTrendFilters($request);
        $canFilterByBranch = $this->salesProfitTrends->canFilterByBranch();

        return Inertia::render('admin/reports/sales-profit-trend', [
            'groups' => $this->salesProfitTrends->groupOptions(),
            'periods' => $this->salesProfitTrends->periodOptions(),
            'branches' => $canFilterByBranch ? $this->salesProfitTrends->branchOptions() : [],
            'isBranchScoped' => ! $canFilterByBranch,
            'filters' => [
                'group_by' => $resolved['group_by'],
                'period' => $resolved['period'],
                'date_from' => $resolved['date_from'],
                'date_to' => $resolved['date_to'],
                'branch_id' => $canFilterByBranch ? $resolved['branch_id'] : null,
            ],
            'report' => $this->salesProfitTrends->build(
                $resolved['group_by'],
                $resolved['period'],
                $resolved['date_from'],
                $resolved['date_to'],
                $resolved['branch_id'],
            ),
        ]);
    }

    public function purchaseReport(Request $request): Response
    {
        $this->authorize(self::PERMISSION_PURCHASE_REPORT);

        $filters = $request->validate([
            'supplier_id' => ['nullable', 'integer', 'exists:suppliers,id'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
        ]);

        if (! isset($filters['date_from']) && ! isset($filters['date_to'])) {
            $filters['date_from'] = now()->startOfMonth()->format('Y-m-d');
            $filters['date_to'] = now()->format('Y-m-d');
        }

        $canFilterByBranch = $this->purchaseReports->canFilterByBranch();
        $filterBranchId = $canFilterByBranch && isset($filters['branch_id']) ? (int) $filters['branch_id'] : null;
        $supplierId = isset($filters['supplier_id']) ? (int) $filters['supplier_id'] : null;

        $report = $this->purchaseReports->build(
            $supplierId,
            $filters['date_from'] ?? null,
            $filters['date_to'] ?? null,
            $filterBranchId,
        );

        return Inertia::render('admin/reports/purchase-report', [
            'suppliers' => $this->purchaseReports->supplierOptions(),
            'branches' => $canFilterByBranch ? $this->purchaseReports->branchOptions() : [],
            'isBranchScoped' => ! $canFilterByBranch,
            'filters' => [
                'supplier_id' => $supplierId,
                'date_from' => $filters['date_from'] ?? null,
                'date_to' => $filters['date_to'] ?? null,
                'branch_id' => $canFilterByBranch ? ($filters['branch_id'] ?? null) : null,
            ],
            ...$report,
        ]);
    }

    public function salesReport(Request $request): Response
    {
        $this->authorize(self::PERMISSION_SALES_REPORT);

        $resolved = $this->resolvedSalesReportFilters($request);
        $canFilterByBranch = $this->salesReports->canFilterByBranch();

        return Inertia::render('admin/reports/sales-report', [
            'types' => $this->salesReports->typeOptions(),
            'branches' => $canFilterByBranch ? $this->salesReports->branchOptions() : [],
            'isBranchScoped' => ! $canFilterByBranch,
            'filters' => [
                'type' => $resolved['type'],
                'date_from' => $resolved['date_from'],
                'date_to' => $resolved['date_to'],
                'branch_id' => $canFilterByBranch ? $resolved['branch_id'] : null,
                'threshold' => $resolved['threshold'],
                'inactive_days' => $resolved['inactive_days'],
            ],
            'report' => $this->salesReports->build(
                $resolved['type'],
                $resolved['date_from'],
                $resolved['date_to'],
                $resolved['branch_id'],
                $resolved['threshold'],
                $resolved['inactive_days'],
            ),
        ]);
    }

    public function salesReportExportExcel(Request $request): BinaryFileResponse
    {
        $this->authorize(self::PERMISSION_SALES_REPORT);

        [$title, $headings, $rows] = $this->salesReportExportTable($request);

        return $this->downloadListExcel(str($title)->slug()->toString(), $headings, $rows);
    }

    public function salesReportExportPdf(Request $request): SymfonyResponse
    {
        $this->authorize(self::PERMISSION_SALES_REPORT);

        [$title, $headings, $rows] = $this->salesReportExportTable($request);

        return $this->downloadListPdf($title, $headings, $rows);
    }

    public function salesReportExportCsv(Request $request): SymfonyResponse
    {
        $this->authorize(self::PERMISSION_SALES_REPORT);

        [$title, $headings, $rows] = $this->salesReportExportTable($request);

        return $this->downloadListCsv(str($title)->slug()->toString(), $headings, $rows);
    }

    /**
     * @return array{
     *     group_by: string,
     *     period: string,
     *     date_from: string|null,
     *     date_to: string|null,
     *     branch_id: int|null
     * }
     */
    private function resolvedSalesProfitTrendFilters(Request $request): array
    {
        $filters = $request->validate([
            'group_by' => ['nullable', 'string', Rule::in($this->salesProfitTrends->allowedGroups())],
            'period' => ['nullable', 'string', Rule::in($this->salesProfitTrends->allowedPeriods())],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
        ]);

        if (! isset($filters['date_from']) && ! isset($filters['date_to'])) {
            $filters['date_from'] = now()->startOfMonth()->format('Y-m-d');
            $filters['date_to'] = now()->format('Y-m-d');
        }

        $canFilterByBranch = $this->salesProfitTrends->canFilterByBranch();

        return [
            'group_by' => $filters['group_by'] ?? SalesProfitTrendService::GROUP_PRODUCT,
            'period' => $filters['period'] ?? SalesProfitTrendService::PERIOD_DAILY,
            'date_from' => $filters['date_from'] ?? null,
            'date_to' => $filters['date_to'] ?? null,
            'branch_id' => $canFilterByBranch && isset($filters['branch_id']) ? (int) $filters['branch_id'] : null,
        ];
    }

    /**
     * @return array{
     *     type: string,
     *     date_from: string|null,
     *     date_to: string|null,
     *     branch_id: int|null,
     *     threshold: int,
     *     inactive_days: int
     * }
     */
    private function resolvedSalesReportFilters(Request $request): array
    {
        $types = $this->salesReports->allowedTypes();

        $filters = $request->validate([
            'type' => ['nullable', 'string', Rule::in($types)],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
            'threshold' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'inactive_days' => ['nullable', 'integer', 'min:1', 'max:3650'],
        ]);

        $type = $filters['type'] ?? SalesReportService::TYPE_DAILY;
        $needsDates = ! in_array($type, [
            SalesReportService::TYPE_STOCK,
            SalesReportService::TYPE_LOW_STOCK,
            SalesReportService::TYPE_DEAD_STOCK,
        ], true);

        if ($needsDates && ! isset($filters['date_from']) && ! isset($filters['date_to'])) {
            $filters['date_from'] = now()->startOfMonth()->format('Y-m-d');
            $filters['date_to'] = now()->format('Y-m-d');
        }

        $canFilterByBranch = $this->salesReports->canFilterByBranch();

        return [
            'type' => $type,
            'date_from' => $filters['date_from'] ?? null,
            'date_to' => $filters['date_to'] ?? null,
            'branch_id' => $canFilterByBranch && isset($filters['branch_id']) ? (int) $filters['branch_id'] : null,
            'threshold' => isset($filters['threshold'])
                ? (int) $filters['threshold']
                : SalesReportService::DEFAULT_LOW_STOCK_THRESHOLD,
            'inactive_days' => isset($filters['inactive_days'])
                ? (int) $filters['inactive_days']
                : SalesReportService::DEFAULT_DEAD_STOCK_DAYS,
        ];
    }

    /**
     * @return array{0: string, 1: list<string>, 2: Collection<int, list<string|int|float|null>>}
     */
    private function salesReportExportTable(Request $request): array
    {
        $resolved = $this->resolvedSalesReportFilters($request);

        $report = $this->salesReports->build(
            $resolved['type'],
            $resolved['date_from'],
            $resolved['date_to'],
            $resolved['branch_id'],
            $resolved['threshold'],
            $resolved['inactive_days'],
        );

        $columns = $report['columns'] ?? [];
        $keys = array_column($columns, 'key');
        $headings = array_column($columns, 'label');

        $rows = collect($report['rows'] ?? [])
            ->map(fn (array $row): array => $this->salesReportExportRow($keys, $row))
            ->values();

        $totals = $report['totals'] ?? [];
        if ($totals !== [] && $keys !== []) {
            $totalRow = $this->salesReportExportRow($keys, $totals);
            $firstKey = $keys[0];
            if (! array_key_exists($firstKey, $totals) || $totals[$firstKey] === null || $totals[$firstKey] === '') {
                $totalRow[0] = 'Total';
            }
            $rows->push($totalRow);
        }

        return [(string) ($report['label'] ?? 'Sales Report'), $headings, $rows];
    }

    /**
     * @param  list<string>  $keys
     * @param  array<string, mixed>  $row
     * @return list<string|int|float|null>
     */
    private function salesReportExportRow(array $keys, array $row): array
    {
        return array_map(function (string $key) use ($row) {
            $value = $row[$key] ?? null;

            if ($value === null || $value === '') {
                return '—';
            }

            if (in_array($key, ['margin', 'discount_pct', 'vat_pct'], true)) {
                return number_format((float) $value, 1).'%';
            }

            if (is_float($value) || (is_numeric($value) && str_contains((string) $value, '.'))) {
                return round((float) $value, 2);
            }

            return $value;
        }, $keys);
    }

    private function authorizeReportProductSearch(): void
    {
        $user = Auth::user();

        abort_unless(
            $user && (
                $user->can(self::PERMISSION_SALES_SUMMARY)
                || $user->can(self::PERMISSION_DATE_WISE_STOCK)
                || $user->can(self::PERMISSION_STOCK_LEDGER)
            ),
            403,
        );
    }
}
