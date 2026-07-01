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

class CustomerReportCurrentDueSheet implements FromCollection, ShouldAutoSize, WithEvents, WithHeadings, WithTitle
{
    private const COLUMN_COUNT = 5;

    private const CURRENCY_COLUMNS = [3, 4, 5];

    /**
     * @param  list<array<string, mixed>>  $dueSales
     * @param  array<string, float>  $totals
     */
    public function __construct(
        private array $dueSales,
        private array $totals,
        private string $customerName,
    ) {}

    public function title(): string
    {
        return 'Current Due';
    }

    /**
     * @return list<string>
     */
    public function headings(): array
    {
        return [
            'Date',
            'Invoice #',
            'Net Amount',
            'Paid Amount',
            'Due Amount',
        ];
    }

    public function collection(): Collection
    {
        $rows = collect($this->dueSales)->map(fn (array $sale) => [
            $sale['date'],
            $sale['invoice_number'],
            $sale['net_amount'],
            $sale['paid_amount'],
            $sale['due_amount'],
        ]);

        $rows->push([
            '',
            'Outstanding Invoice Due',
            '',
            '',
            $this->totals['current_due'],
        ]);

        $rows->push([
            '',
            'Account Due Balance',
            '',
            '',
            $this->totals['account_balance'],
        ]);

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
                    'Current Due',
                    CustomerReportSheetStyles::subtitle($this->customerName),
                    self::COLUMN_COUNT,
                );

                $headerRow = 4;
                $lastColumn = 'E';
                $highestRow = $sheet->getHighestRow();

                CustomerReportSheetStyles::applyTableHeader($sheet, "A{$headerRow}:{$lastColumn}{$headerRow}");

                $totalRows = [$highestRow - 1, $highestRow];
                $dataEndRow = $highestRow - 2;

                if ($dataEndRow > $headerRow) {
                    CustomerReportSheetStyles::applyDataTable(
                        $sheet,
                        $headerRow + 1,
                        $dataEndRow,
                        self::COLUMN_COUNT,
                        self::CURRENCY_COLUMNS,
                    );
                }

                CustomerReportSheetStyles::applyTotalRows(
                    $sheet,
                    $totalRows,
                    self::COLUMN_COUNT,
                    [5],
                );

                CustomerReportSheetStyles::freezeBelowHeader($sheet, $headerRow);
            },
        ];
    }
}
