<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Services\ReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class ReportController extends Controller
{
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

    public function __construct(private ReportService $reports) {}

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

    public function dateWiseStock(Request $request): Response
    {
        $this->authorize(self::PERMISSION_DATE_WISE_STOCK);

        $filters = $request->validate([
            'product_id' => ['nullable', 'integer', 'exists:products,id'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],
        ]);

        return Inertia::render('admin/reports/date-wise-stock', [
            'products' => $this->reports->productOptions(),
            'filters' => $filters,
            'entries' => $this->reports->dateWiseStock(
                isset($filters['product_id']) ? (int) $filters['product_id'] : null,
                $filters['date_from'] ?? null,
                $filters['date_to'] ?? null,
            ),
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
        $this->authorize(self::PERMISSION_SALES_SUMMARY);

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

    public function balanceSheet(Request $request): Response
    {
        $this->authorize(self::PERMISSION_BALANCE_SHEET);

        $filters = $request->validate([
            'as_of' => ['nullable', 'date'],
        ]);

        $asOf = $filters['as_of'] ?? now()->format('Y-m-d');

        return Inertia::render('admin/reports/balance-sheet', [
            'filters' => ['as_of' => $asOf],
            'sheet' => $this->reports->balanceSheet($asOf),
        ]);
    }
}
