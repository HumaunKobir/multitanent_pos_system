<?php

namespace App\Exports\Sheets;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

class CustomerReportCollectionsSheet implements FromCollection, ShouldAutoSize, WithHeadings, WithTitle
{
    /**
     * @param  list<array<string, mixed>>  $collections
     * @param  array<string, float>  $totals
     */
    public function __construct(
        private array $collections,
        private array $totals,
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
            $collection['date'],
            $collection['invoice_number'],
            $collection['amount'],
            $collection['comment'],
            $collection['created_by'],
        ]);

        if ($rows->isNotEmpty()) {
            $rows->push([
                '',
                'Total Collected',
                $this->totals['collections'],
                '',
                '',
            ]);
        }

        return $rows;
    }
}
