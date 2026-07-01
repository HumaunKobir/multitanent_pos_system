<?php

namespace App\Exports\Sheets;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

class BusinessSessionAccountBalancesSheet implements FromCollection, ShouldAutoSize, WithHeadings, WithTitle
{
    /**
     * @param  list<array<string, mixed>>  $rows
     */
    public function __construct(private array $rows) {}

    public function title(): string
    {
        return 'Account Balances';
    }

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
        return collect($this->rows)->map(fn (array $row) => [
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
    }
}
