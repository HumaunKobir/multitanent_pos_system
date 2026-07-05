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

class CustomerReportBulkSalesSheet implements FromCollection, ShouldAutoSize, WithEvents, WithHeadings, WithTitle
{
    private const COLUMN_COUNT = 11;

    private const CURRENCY_COLUMNS = [5, 6, 7, 8, 9, 10];

    /**
     * @param  list<array<string, mixed>>  $sales
     * @param  array<string, float>  $totals
     */
    public function __construct(
        private array $sales,
        private array $totals,
        private string $subtitle,
    ) {}

    public function title(): string
    {
        return 'Sales';
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
            'Gross Amount',
            'Discount',
            'VAT',
            'Net Amount',
            'Paid Amount',
            'Due Amount',
            'Comment',
        ];
    }

    public function collection(): Collection
    {
        $rows = collect($this->sales)->map(fn (array $sale) => [
            $sale['customer'],
            $sale['phone'],
            $sale['date'],
            $sale['invoice_number'],
            $sale['gross_amount'],
            $sale['discount'],
            $sale['vat'],
            $sale['net_amount'],
            $sale['paid_amount'],
            $sale['due_amount'],
            $sale['comment'],
        ]);

        if ($rows->isNotEmpty()) {
            $rows->push([
                '',
                '',
                '',
                'Totals',
                '',
                '',
                '',
                $this->totals['sales_net'],
                $this->totals['sales_paid'],
                $this->totals['sales_due'],
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
                    'Sales History',
                    $this->subtitle,
                    self::COLUMN_COUNT,
                );

                $headerRow = 4;
                $lastColumn = 'K';
                $highestRow = $sheet->getHighestRow();

                CustomerReportSheetStyles::applyTableHeader($sheet, "A{$headerRow}:{$lastColumn}{$headerRow}");

                $dataEndRow = $highestRow;
                $totalRows = [];

                if ($highestRow > $headerRow && collect($this->sales)->isNotEmpty()) {
                    $dataEndRow = $highestRow - 1;
                    $totalRows[] = $highestRow;
                }

                if ($dataEndRow > $headerRow) {
                    CustomerReportSheetStyles::applyDataTable(
                        $sheet,
                        $headerRow + 1,
                        $dataEndRow,
                        self::COLUMN_COUNT,
                        self::CURRENCY_COLUMNS,
                    );
                }

                if ($totalRows !== []) {
                    CustomerReportSheetStyles::applyTotalRows(
                        $sheet,
                        $totalRows,
                        self::COLUMN_COUNT,
                        [8, 9, 10],
                    );
                }

                CustomerReportSheetStyles::freezeBelowHeader($sheet, $headerRow);
            },
        ];
    }
}
