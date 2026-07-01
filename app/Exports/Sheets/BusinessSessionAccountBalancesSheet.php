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

class BusinessSessionAccountBalancesSheet implements FromCollection, ShouldAutoSize, WithEvents, WithHeadings, WithTitle
{
    private const COLUMN_COUNT = 10;

    /** @var list<int> */
    private const CURRENCY_COLUMNS = [3, 4, 5, 6, 7, 8, 9, 10];

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
        return 'Account Balances';
    }

    /**
     * @return list<string>
     */
    public function headings(): array
    {
        return [
            'Account',
            'Type',
            'Opening Balance',
            'Total Debit',
            'Total Credit',
            'Received',
            'Paid',
            'Transfer In',
            'Transfer Out',
            'Closing Balance',
        ];
    }

    public function collection(): Collection
    {
        $rows = collect($this->rows)->map(fn (array $row) => [
            $row['account_name'] ?? '',
            $row['account_type'] ?? '',
            $row['opening_balance'] ?? 0,
            $row['total_debit'] ?? 0,
            $row['total_credit'] ?? 0,
            $row['total_received'] ?? 0,
            $row['total_paid'] ?? 0,
            $row['total_transfer_in'] ?? 0,
            $row['total_transfer_out'] ?? 0,
            $row['closing_balance'] ?? 0,
        ]);

        if ($rows->isNotEmpty()) {
            $rows->push([
                'Totals',
                '',
                round($rows->sum(fn (array $row) => (float) $row[2]), 2),
                round($rows->sum(fn (array $row) => (float) $row[3]), 2),
                round($rows->sum(fn (array $row) => (float) $row[4]), 2),
                round($rows->sum(fn (array $row) => (float) $row[5]), 2),
                round($rows->sum(fn (array $row) => (float) $row[6]), 2),
                round($rows->sum(fn (array $row) => (float) $row[7]), 2),
                round($rows->sum(fn (array $row) => (float) $row[8]), 2),
                round($rows->sum(fn (array $row) => (float) $row[9]), 2),
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
                    'Account Balances',
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
