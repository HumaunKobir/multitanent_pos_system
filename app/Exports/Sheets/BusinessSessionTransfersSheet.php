<?php

namespace App\Exports\Sheets;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

class BusinessSessionTransfersSheet implements FromCollection, ShouldAutoSize, WithHeadings, WithTitle
{
    /**
     * @param  list<array<string, mixed>>  $rows
     */
    public function __construct(private array $rows) {}

    public function title(): string
    {
        return 'Balance Transfers';
    }

    public function headings(): array
    {
        return [
            'Date',
            'Time',
            'Reference',
            'From Account',
            'To Account',
            'Amount',
            'Method',
            'Created By',
            'Remarks',
        ];
    }

    public function collection(): Collection
    {
        return collect($this->rows)->map(fn (array $row) => [
            $row['date'] ?? '',
            $row['time'] ?? '',
            $row['reference'] ?? '',
            $row['from_account'] ?? '',
            $row['to_account'] ?? '',
            $row['amount'] ?? 0,
            $row['method'] ?? '',
            $row['created_by'] ?? '',
            $row['remarks'] ?? '',
        ]);
    }
}
