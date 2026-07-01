<?php

namespace App\Exports\Sheets;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithTitle;

class BusinessSessionSummarySheet implements FromArray, ShouldAutoSize, WithTitle
{
    /**
     * @param  array<string, mixed>  $report
     */
    public function __construct(private array $report) {}

    public function title(): string
    {
        return 'Session Summary';
    }

    public function array(): array
    {
        $session = $this->report['session'] ?? [];
        $closing = $this->report['closing_summary'] ?? [];

        return [
            ['Business Session Closing Report'],
            [],
            ['Session Number', $session['session_number'] ?? ''],
            ['Session Date', $session['session_date'] ?? ''],
            ['Branch', $session['branch_name'] ?? ''],
            ['Started By', $session['started_by'] ?? ''],
            ['Session Start', $session['started_at'] ?? ''],
            ['Session Close', $session['closed_at'] ?? ''],
            ['Closed By', $session['closed_by'] ?? ''],
            ['Duration', $session['duration'] ?? ''],
            ['Status', $session['status'] ?? ''],
            [],
            ['Total Opening Balance', $closing['total_opening_balance'] ?? 0],
            ['Total Receipts', $closing['total_receipts'] ?? 0],
            ['Total Payments', $closing['total_payments'] ?? 0],
            ['Total Income', $closing['total_income'] ?? 0],
            ['Total Expenses', $closing['total_expenses'] ?? 0],
            ['Total Transfer In', $closing['total_transfer_in'] ?? 0],
            ['Total Transfer Out', $closing['total_transfer_out'] ?? 0],
            ['Total Closing Balance', $closing['total_closing_balance'] ?? 0],
        ];
    }
}
