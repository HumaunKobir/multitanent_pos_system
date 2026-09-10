<?php

namespace App\Exports\Support;

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class CustomerReportSheetStyles
{
    private const HEADER_BG = '1F4E79';

    private const HEADER_FG = 'FFFFFF';

    private const ALT_ROW_BG = 'F2F7FB';

    private const TOTAL_ROW_BG = 'E8EEF4';

    private const LABEL_BG = 'F5F7FA';

    private const SECTION_BG = 'D6E4F0';

    private const BORDER_COLOR = 'B4C6E7';

    public static function applyReportHeader(Worksheet $sheet, string $title, string $subtitle, int $columnCount): void
    {
        $lastColumn = self::columnLetter($columnCount);

        $sheet->mergeCells("A1:{$lastColumn}1");
        $sheet->setCellValue('A1', $title);

        $sheet->getStyle('A1')->applyFromArray([
            'font' => [
                'bold' => true,
                'size' => 16,
                'color' => ['rgb' => self::HEADER_BG],
            ],
            'alignment' => [
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
        ]);

        $sheet->mergeCells("A2:{$lastColumn}2");
        $sheet->setCellValue('A2', $subtitle);

        $sheet->getStyle('A2')->applyFromArray([
            'font' => [
                'size' => 10,
                'color' => ['rgb' => '666666'],
                'italic' => true,
            ],
        ]);

        $sheet->getRowDimension(1)->setRowHeight(28);
        $sheet->getRowDimension(2)->setRowHeight(18);
    }

    public static function applySectionTitle(Worksheet $sheet, int $row, string $title, int $columnCount): void
    {
        $lastColumn = self::columnLetter($columnCount);
        $range = "A{$row}:{$lastColumn}{$row}";

        $sheet->mergeCells($range);
        $sheet->setCellValue("A{$row}", $title);

        $sheet->getStyle($range)->applyFromArray([
            'font' => [
                'bold' => true,
                'size' => 11,
                'color' => ['rgb' => self::HEADER_BG],
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => self::SECTION_BG],
            ],
            'alignment' => [
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
        ]);

        $sheet->getRowDimension($row)->setRowHeight(22);
    }

    public static function applyTableHeader(Worksheet $sheet, string $range): void
    {
        $sheet->getStyle($range)->applyFromArray([
            'font' => [
                'bold' => true,
                'color' => ['rgb' => self::HEADER_FG],
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => self::HEADER_BG],
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
            'borders' => self::thinBorder(),
        ]);

        $headerRow = (int) preg_replace('/[^0-9]/', '', explode(':', $range)[0]);
        $sheet->getRowDimension($headerRow)->setRowHeight(22);
    }

    /**
     * @param  list<int>  $currencyColumns  1-based column indexes
     */
    public static function applyDataTable(
        Worksheet $sheet,
        int $startRow,
        int $endRow,
        int $columnCount,
        array $currencyColumns = [],
    ): void {
        if ($endRow < $startRow) {
            return;
        }

        $lastColumn = self::columnLetter($columnCount);
        $range = "A{$startRow}:{$lastColumn}{$endRow}";

        $sheet->getStyle($range)->applyFromArray([
            'borders' => self::thinBorder(),
            'alignment' => [
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
        ]);

        for ($row = $startRow; $row <= $endRow; $row++) {
            if (($row - $startRow) % 2 === 1) {
                $sheet->getStyle("A{$row}:{$lastColumn}{$row}")->applyFromArray([
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => ['rgb' => self::ALT_ROW_BG],
                    ],
                ]);
            }
        }

        foreach ($currencyColumns as $column) {
            $columnLetter = self::columnLetter($column);
            $sheet->getStyle("{$columnLetter}{$startRow}:{$columnLetter}{$endRow}")
                ->getNumberFormat()
                ->setFormatCode('#,##0.00');
        }
    }

    /**
     * @param  list<int>  $currencyColumns  1-based column indexes
     */
    public static function applyTotalRows(
        Worksheet $sheet,
        array $rows,
        int $columnCount,
        array $currencyColumns = [],
    ): void {
        $lastColumn = self::columnLetter($columnCount);

        foreach ($rows as $row) {
            $range = "A{$row}:{$lastColumn}{$row}";

            $sheet->getStyle($range)->applyFromArray([
                'font' => ['bold' => true],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => self::TOTAL_ROW_BG],
                ],
                'borders' => [
                    'top' => [
                        'borderStyle' => Border::BORDER_MEDIUM,
                        'color' => ['rgb' => self::HEADER_BG],
                    ],
                    'bottom' => self::borderSide(),
                    'left' => self::borderSide(),
                    'right' => self::borderSide(),
                ],
            ]);

            foreach ($currencyColumns as $column) {
                $columnLetter = self::columnLetter($column);
                $sheet->getStyle("{$columnLetter}{$row}")
                    ->getNumberFormat()
                    ->setFormatCode('#,##0.00');
            }
        }
    }

    public static function applyLabelValueTable(
        Worksheet $sheet,
        int $headerRow,
        int $startRow,
        int $endRow,
        array $currencyRows = [],
    ): void {
        self::applyTableHeader($sheet, "A{$headerRow}:B{$headerRow}");

        for ($row = $startRow; $row <= $endRow; $row++) {
            $sheet->getStyle("A{$row}")->applyFromArray([
                'font' => ['bold' => true],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => self::LABEL_BG],
                ],
            ]);

            if (in_array($row, $currencyRows, true)) {
                $sheet->getStyle("B{$row}")
                    ->getNumberFormat()
                    ->setFormatCode('#,##0.00');
            }
        }

        $sheet->getStyle("A{$startRow}:B{$endRow}")->applyFromArray([
            'borders' => self::thinBorder(),
            'alignment' => [
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
        ]);

        $sheet->getColumnDimension('A')->setWidth(24);
        $sheet->getColumnDimension('B')->setAutoSize(true);
    }

    public static function freezeBelowHeader(Worksheet $sheet, int $headerRow): void
    {
        $sheet->freezePane('A'.($headerRow + 1));
    }

    public static function subtitle(string $customerName, ?string $dateFrom = null, ?string $dateTo = null): string
    {
        $appName = (string) config('app.name', 'POS SYSTEM');
        $parts = [
            $appName,
            'Customer: '.$customerName,
        ];

        if ($dateFrom !== null || $dateTo !== null) {
            $parts[] = self::dateRangeLabel($dateFrom, $dateTo);
        }

        $parts[] = 'Generated on '.now()->format('M d, Y h:i A');

        return implode(' · ', $parts);
    }

    public static function bulkSubtitle(int $customerCount, ?string $dateFrom = null, ?string $dateTo = null): string
    {
        $appName = (string) config('app.name', 'POS SYSTEM');
        $parts = [
            $appName,
            $customerCount.' customers',
        ];

        if ($dateFrom !== null || $dateTo !== null) {
            $parts[] = self::dateRangeLabel($dateFrom, $dateTo);
        }

        $parts[] = 'Generated on '.now()->format('M d, Y h:i A');

        return implode(' · ', $parts);
    }

    public static function dateRangeLabel(?string $dateFrom, ?string $dateTo): string
    {
        if ($dateFrom !== null && $dateTo !== null) {
            return "Period: {$dateFrom} to {$dateTo}";
        }

        if ($dateFrom !== null) {
            return "From: {$dateFrom}";
        }

        return "Until: {$dateTo}";
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private static function thinBorder(): array
    {
        return [
            'allBorders' => self::borderSide(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function borderSide(): array
    {
        return [
            'borderStyle' => Border::BORDER_THIN,
            'color' => ['rgb' => self::BORDER_COLOR],
        ];
    }

    private static function columnLetter(int $columnIndex): string
    {
        return Coordinate::stringFromColumnIndex($columnIndex);
    }
}
