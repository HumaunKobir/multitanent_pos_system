<?php

namespace App\Exports\Sheets;

use App\Exports\Support\BusinessSessionSheetStyles;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

class BusinessSessionExpensesSheet implements FromCollection, ShouldAutoSize, WithEvents, WithHeadings, WithTitle
{
    private const COLUMN_COUNT = 5;

    /** @var list<int> */
    private const CURRENCY_COLUMNS = [4];

    /**
     * @param  list<array<string, mixed>>  $rows
     * @param  array<string, mixed>  $session
     */
    public function __construct(
        private array $rows,
        private array $session,
    ) {}

    public function title(): string
    {
        return 'Expense Details';
    }

    /**
     * @return list<string>
     */
    public function headings(): array
    {
        return ['Category', 'Expense Account', 'Transactions', 'Total Amount', 'Payment Account'];
    }

    public function collection(): Collection
    {
        $rows = collect($this->rows)->map(fn (array $row) => [
            $row['category'] ?? '',
            $row['account_name'] ?? '',
            $row['transaction_count'] ?? 0,
            $row['total_amount'] ?? 0,
            $row['payment_account'] ?? '',
        ]);

        if ($rows->isNotEmpty()) {
            $rows->push([
                '',
                'Total',
                $rows->sum(fn (array $row) => (int) $row[2]),
                round($rows->sum(fn (array $row) => (float) $row[3]), 2),
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

                BusinessSessionSheetStyles::applyReportHeader(
                    $sheet,
                    'Expense Details',
                    BusinessSessionSheetStyles::subtitle($this->session),
                    self::COLUMN_COUNT,
                );

                $headerRow = 4;
                $lastColumn = Coordinate::stringFromColumnIndex(self::COLUMN_COUNT);
                $highestRow = $sheet->getHighestRow();
                $dataEndRow = $highestRow;
                $totalRows = [];

                BusinessSessionSheetStyles::applyTableHeader($sheet, "A{$headerRow}:{$lastColumn}{$headerRow}");

                if ($highestRow > $headerRow && collect($this->rows)->isNotEmpty()) {
                    $dataEndRow = $highestRow - 1;
                    $totalRows[] = $highestRow;
                }

                if ($dataEndRow > $headerRow) {
                    BusinessSessionSheetStyles::applyDataTable(
                        $sheet,
                        $headerRow + 1,
                        $dataEndRow,
                        self::COLUMN_COUNT,
                        self::CURRENCY_COLUMNS,
                    );
                }

                if ($totalRows !== []) {
                    BusinessSessionSheetStyles::applyTotalRows(
                        $sheet,
                        $totalRows,
                        self::COLUMN_COUNT,
                        self::CURRENCY_COLUMNS,
                    );
                }

                BusinessSessionSheetStyles::freezeBelowHeader($sheet, $headerRow);
            },
        ];
    }
}
