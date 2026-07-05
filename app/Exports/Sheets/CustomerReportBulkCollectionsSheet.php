<?php

namespace App\Exports\Sheets;

use App\Exports\Support\CustomerReportSheetStyles;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;

class CustomerReportBulkCollectionsSheet implements FromCollection, ShouldAutoSize, WithEvents, WithHeadings, WithTitle
{
    private const COLUMN_COUNT = 7;

    /**
     * @param  list<array<string, mixed>>  $collections
     * @param  array<string, float>  $totals
     */
    public function __construct(
        private array $collections,
        private array $totals,
        private string $subtitle,
    ) {}

    public function title(): string
    {
        return 'Due Collections';
    }

    /**
     * @return list<string>
     */
    public function headings(): array
    {
        return [
            'Customer',
            'Phone',
            'Date',
            'Invoice #',
            'Amount',
            'Comment',
            'Collected By',
        ];
    }

    public function collection(): Collection
    {
        $rows = collect($this->collections)->map(fn (array $collection) => [
            $collection['customer'],
            $collection['phone'],
            $collection['date'],
            $collection['invoice_number'],
            $collection['amount'],
            $collection['comment'],
            $collection['created_by'],
        ]);

        if ($rows->isNotEmpty()) {
            $rows->push([
                '',
                '',
                '',
                'Total Collected',
                $this->totals['collections'],
                '',
                '',
            ]);
        }

        return $rows;
    }

    /**
     * @return array<class-string, callable>
     */
    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event): void {
                $sheet = $event->sheet->getDelegate();
                $sheet->insertNewRowBefore(1, 3);

                CustomerReportSheetStyles::applyReportHeader(
                    $sheet,
                    'Due Collections',
                    $this->subtitle,
                    self::COLUMN_COUNT,
                );

                $headerRow = 4;
                $lastColumn = 'G';
                $highestRow = $sheet->getHighestRow();

                CustomerReportSheetStyles::applyTableHeader($sheet, "A{$headerRow}:{$lastColumn}{$headerRow}");

                $dataEndRow = $highestRow;
                $totalRows = [];

                if ($highestRow > $headerRow && collect($this->collections)->isNotEmpty()) {
                    $dataEndRow = $highestRow - 1;
                    $totalRows[] = $highestRow;
                }

                if ($dataEndRow > $headerRow) {
                    CustomerReportSheetStyles::applyDataTable(
                        $sheet,
                        $headerRow + 1,
                        $dataEndRow,
                        self::COLUMN_COUNT,
                        currencyColumns: [5],
                    );
                }

                if ($totalRows !== []) {
                    CustomerReportSheetStyles::applyTotalRows(
                        $sheet,
                        $totalRows,
                        self::COLUMN_COUNT,
                        currencyColumns: [5],
                    );
                }

                CustomerReportSheetStyles::freezeBelowHeader($sheet, $headerRow);
            },
        ];
    }
}
