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

class CustomerReportBulkSummarySheet implements FromCollection, ShouldAutoSize, WithEvents, WithHeadings, WithTitle
{
    private const COLUMN_COUNT = 7;

    private const CURRENCY_COLUMNS = [5, 6];

    /**
     * @param  list<array<string, mixed>>  $customers
     */
    public function __construct(
        private array $customers,
        private string $subtitle,
    ) {}

    public function title(): string
    {
        return 'Customers';
    }

    /**
     * @return list<string>
     */
    public function headings(): array
    {
        return [
            'Name',
            'Phone',
            'Email',
            'Address',
            'Coin Balance',
            'Due Balance',
            'Status',
        ];
    }

    public function collection(): Collection
    {
        return collect($this->customers)->map(fn (array $customer) => [
            $customer['name'] ?? '—',
            $customer['phone'] ?? '—',
            $customer['email'] ?? '—',
            $customer['address'] ?? '—',
            $customer['coin_balance'] ?? 0,
            $customer['due_balance'] ?? 0,
            $customer['status'] ?? '—',
        ]);
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
                    'Customer Summary',
                    $this->subtitle,
                    self::COLUMN_COUNT,
                );

                $headerRow = 4;
                $lastColumn = 'G';
                $highestRow = $sheet->getHighestRow();

                CustomerReportSheetStyles::applyTableHeader($sheet, "A{$headerRow}:{$lastColumn}{$headerRow}");

                if ($highestRow > $headerRow) {
                    CustomerReportSheetStyles::applyDataTable(
                        $sheet,
                        $headerRow + 1,
                        $highestRow,
                        self::COLUMN_COUNT,
                        self::CURRENCY_COLUMNS,
                    );
                }

                CustomerReportSheetStyles::freezeBelowHeader($sheet, $headerRow);
            },
        ];
    }
}
