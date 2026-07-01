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
    public function __construct(private array $report) {}

    /**
     * @return list<object>
     */
    public function sheets(): array
    {
        return [
            new BusinessSessionSummarySheet($this->report),
            new BusinessSessionAccountBalancesSheet($this->report['account_balances'] ?? []),
            new BusinessSessionTransactionsSheet($this->report['transactions'] ?? []),
            new BusinessSessionIncomeSheet($this->report['income_summary'] ?? []),
            new BusinessSessionExpensesSheet($this->report['expense_summary'] ?? []),
            new BusinessSessionTransfersSheet($this->report['transfers'] ?? []),
        ];
    }
}
