<?php

namespace App\Exports\Support;

use Carbon\Carbon;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class BusinessSessionSheetStyles
{
    public static function subtitle(array $session): string
    {
        $appName = (string) config('app.name', 'POS SYSTEM');
        $sessionNumber = (string) ($session['session_number'] ?? '—');
        $branch = (string) ($session['branch_name'] ?? '—');

        return sprintf(
            '%s · Session: %s · Branch: %s · Generated on %s',
            $appName,
            $sessionNumber,
            $branch,
            now()->format('M d, Y h:i A'),
        );
    }

    public static function formatDisplayDate(?string $date): string
    {
        if ($date === null || $date === '') {
            return '—';
        }

        try {
            return Carbon::parse($date)->format('d F, Y');
        } catch (\Throwable) {
            return $date;
        }
    }

    public static function formatDisplayDateTime(?string $value): string
    {
        if ($value === null || $value === '') {
            return '—';
        }

        try {
            return Carbon::parse($value)->format('d F, Y g:i A');
        } catch (\Throwable) {
            return $value;
        }
    }

    public static function applyReportHeader(Worksheet $sheet, string $title, string $subtitle, int $columnCount): void
    {
        CustomerReportSheetStyles::applyReportHeader($sheet, $title, $subtitle, $columnCount);
    }

    public static function applySectionTitle(Worksheet $sheet, int $row, string $title, int $columnCount): void
    {
        CustomerReportSheetStyles::applySectionTitle($sheet, $row, $title, $columnCount);
    }

    public static function applyTableHeader(Worksheet $sheet, string $range): void
    {
        CustomerReportSheetStyles::applyTableHeader($sheet, $range);
    }

    /**
     * @param  list<int>  $currencyColumns
     */
    public static function applyDataTable(
        Worksheet $sheet,
        int $startRow,
        int $endRow,
        int $columnCount,
        array $currencyColumns = [],
    ): void {
        CustomerReportSheetStyles::applyDataTable($sheet, $startRow, $endRow, $columnCount, $currencyColumns);
    }

    /**
     * @param  list<int>  $rows
     * @param  list<int>  $currencyColumns
     */
    public static function applyTotalRows(
        Worksheet $sheet,
        array $rows,
        int $columnCount,
        array $currencyColumns = [],
    ): void {
        CustomerReportSheetStyles::applyTotalRows($sheet, $rows, $columnCount, $currencyColumns);
    }

    /**
     * @param  list<int>  $currencyRows
     */
    public static function applyLabelValueTable(
        Worksheet $sheet,
        int $headerRow,
        int $startRow,
        int $endRow,
        array $currencyRows = [],
    ): void {
        CustomerReportSheetStyles::applyLabelValueTable($sheet, $headerRow, $startRow, $endRow, $currencyRows);
    }

    public static function freezeBelowHeader(Worksheet $sheet, int $headerRow): void
    {
        CustomerReportSheetStyles::freezeBelowHeader($sheet, $headerRow);
    }
}
