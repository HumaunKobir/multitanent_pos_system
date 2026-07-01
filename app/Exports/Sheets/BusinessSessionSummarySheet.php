<?php

namespace App\Exports\Sheets;

use App\Exports\Support\BusinessSessionSheetStyles;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;

class BusinessSessionSummarySheet implements FromArray, ShouldAutoSize, WithEvents, WithTitle
{
    private const SESSION_SECTION_ROW = 4;

    private const SESSION_HEADER_ROW = 5;

    private const SESSION_DATA_START_ROW = 6;

    private const SESSION_DATA_END_ROW = 15;

    private const FINANCIAL_SECTION_ROW = 17;

    private const FINANCIAL_HEADER_ROW = 18;

    private const FINANCIAL_DATA_START_ROW = 19;

    private const FINANCIAL_DATA_END_ROW = 26;

    /**
     * @param  array<string, mixed>  $report
     */
    public function __construct(private array $report) {}

    public function title(): string
    {
        return 'Session Summary';
    }

    /**
     * @return list<list<string|float|null>>
     */
    public function array(): array
    {
        $session = $this->report['session'] ?? [];
        $closing = $this->report['closing_summary'] ?? [];

        return [
            ['Business Session Closing Report'],
            [BusinessSessionSheetStyles::subtitle($session)],
            ['', ''],
            ['Session Information'],
            ['Field', 'Value'],
            ['Session Number', $session['session_number'] ?? '—'],
            ['Session Date', BusinessSessionSheetStyles::formatDisplayDate($session['session_date'] ?? null)],
            ['Branch', $session['branch_name'] ?? '—'],
            ['Started By', $session['started_by'] ?? '—'],
            ['Session Start', BusinessSessionSheetStyles::formatDisplayDateTime($session['started_at'] ?? null)],
            ['Session Close', BusinessSessionSheetStyles::formatDisplayDateTime($session['closed_at'] ?? null)],
            ['Closed By', $session['closed_by'] ?? '—'],
            ['Duration', $session['duration'] ?? '—'],
            ['Status', $session['status'] ?? '—'],
            ['Opening Method', $session['opening_method'] ?? '—'],
            ['', ''],
            ['Financial Summary'],
            ['Metric', 'Amount'],
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

    /**
     * @return array<class-string, callable>
     */
    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event): void {
                $sheet = $event->sheet->getDelegate();
                $session = $this->report['session'] ?? [];

                BusinessSessionSheetStyles::applyReportHeader(
                    $sheet,
                    'Business Session Closing Report',
                    BusinessSessionSheetStyles::subtitle($session),
                    2,
                );

                BusinessSessionSheetStyles::applySectionTitle(
                    $sheet,
                    self::SESSION_SECTION_ROW,
                    'Session Information',
                    2,
                );

                BusinessSessionSheetStyles::applyLabelValueTable(
                    $sheet,
                    self::SESSION_HEADER_ROW,
                    self::SESSION_DATA_START_ROW,
                    self::SESSION_DATA_END_ROW,
                );

                BusinessSessionSheetStyles::applySectionTitle(
                    $sheet,
                    self::FINANCIAL_SECTION_ROW,
                    'Financial Summary',
                    2,
                );

                BusinessSessionSheetStyles::applyLabelValueTable(
                    $sheet,
                    self::FINANCIAL_HEADER_ROW,
                    self::FINANCIAL_DATA_START_ROW,
                    self::FINANCIAL_DATA_END_ROW,
                    currencyRows: range(self::FINANCIAL_DATA_START_ROW, self::FINANCIAL_DATA_END_ROW),
                );
            },
        ];
    }
}
