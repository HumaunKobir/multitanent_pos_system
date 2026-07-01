<?php

namespace App\Exports\Sheets;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

class BusinessSessionTransactionsSheet implements FromCollection, ShouldAutoSize, WithHeadings, WithTitle
{
    /**
     * @param  list<array<string, mixed>>  $rows
     */
    public function __construct(private array $rows) {}

    public function title(): string
    {
        return 'All Transactions';
    }

    public function headings(): array
    {
        return [
            'Date',
            'Time',
            'Reference',
            'Type',
            'Source Account',
            'Destination Account',
            'Description',
            'Debit',
            'Credit',
            'Created By',
            'Branch',
            'Approval Status',
            'Deleted',
        ];
    }

    public function collection(): Collection
    {
        return collect($this->rows)->map(fn (array $row) => [
            $row['date'] ?? '',
            $row['time'] ?? '',
            $row['reference'] ?? '',
            $row['type'] ?? '',
            $row['source_account'] ?? '',
            $row['destination_account'] ?? '',
            $row['description'] ?? '',
            $row['debit'] ?? 0,
            $row['credit'] ?? 0,
            $row['created_by'] ?? '',
            $row['branch'] ?? '',
            $row['approval_status'] ?? '',
            ($row['is_deleted'] ?? false) ? 'Yes' : 'No',
        ]);
    }
}
