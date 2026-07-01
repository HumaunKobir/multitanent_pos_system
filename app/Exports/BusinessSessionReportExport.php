<?php

namespace App\Exports;

use App\Exports\Sheets\BusinessSessionAccountBalancesSheet;
use App\Exports\Sheets\BusinessSessionExpensesSheet;
use App\Exports\Sheets\BusinessSessionIncomeSheet;
use App\Exports\Sheets\BusinessSessionSummarySheet;
use App\Exports\Sheets\BusinessSessionTransactionsSheet;
use App\Exports\Sheets\BusinessSessionTransfersSheet;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class BusinessSessionReportExport implements WithMultipleSheets
{
    /**
     * @param  array<string, mixed>  $report
     */
    public function __construct(
        private array $report,
        private bool $includeIncomeExpenseSummaries = true,
    ) {}

    /**
     * @return list<object>
     */
    public function sheets(): array
    {
        $session = $this->report['session'] ?? [];

        $sheets = [
            new BusinessSessionSummarySheet($this->report),
            new BusinessSessionAccountBalancesSheet($this->report['account_balances'] ?? [], $session),
            new BusinessSessionTransactionsSheet($this->report['transactions'] ?? [], $session),
        ];

        if ($this->includeIncomeExpenseSummaries) {
            $sheets[] = new BusinessSessionIncomeSheet($this->report['income_summary'] ?? [], $session);
            $sheets[] = new BusinessSessionExpensesSheet($this->report['expense_summary'] ?? [], $session);
        }

        $sheets[] = new BusinessSessionTransfersSheet($this->report['transfers'] ?? [], $session);

        return $sheets;
    }
}
