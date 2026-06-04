<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Services\ReportService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ReportController extends Controller
{
    public function __construct(private ReportService $reports) {}

    public function customerLedger(Request $request): Response
    {
        $this->authorize('report.customer-ledger.view');

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
        $this->authorize('report.cash-flow.view');

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
        $this->authorize('report.cash-flow-summary.view');

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
        $this->authorize('report.daily-transactions.view');

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
        $this->authorize('report.date-wise-stock.view');

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
        $this->authorize('report.daily-summary.view');

        $filters = $request->validate([
            'date' => ['nullable', 'date'],
        ]);

        $date = $filters['date'] ?? now()->format('Y-m-d');

        return Inertia::render('admin/reports/daily-summary', [
            'filters' => ['date' => $date],
            'summary' => $this->reports->dailySummary($date),
        ]);
    }

    public function accountLedger(Request $request): Response
    {
        $this->authorize('report.account-ledger.view');

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
        $this->authorize('report.account-transactions.view');

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

    public function balanceSheet(Request $request): Response
    {
        $this->authorize('report.balance-sheet.view');

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
