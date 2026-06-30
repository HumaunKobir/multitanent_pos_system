<?php

namespace App\Exports\Sheets;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

class CustomerReportSalesSheet implements FromCollection, ShouldAutoSize, WithHeadings, WithTitle
{
    /**
     * @param  list<array<string, mixed>>  $sales
     * @param  array<string, float>  $totals
     */
    public function __construct(
        private array $sales,
        private array $totals,
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
}
