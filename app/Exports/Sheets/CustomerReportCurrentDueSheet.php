<?php

namespace App\Exports\Sheets;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

class CustomerReportCurrentDueSheet implements FromCollection, ShouldAutoSize, WithHeadings, WithTitle
{
    /**
     * @param  list<array<string, mixed>>  $dueSales
     * @param  array<string, float>  $totals
     */
    public function __construct(
        private array $dueSales,
        private array $totals,
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
}
