<?php

namespace App\Exports\Sheets;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

class BusinessSessionExpensesSheet implements FromCollection, ShouldAutoSize, WithHeadings, WithTitle
{
    /**
     * @param  list<array<string, mixed>>  $rows
     */
    public function __construct(private array $rows) {}

    public function title(): string
    {
        return 'Expense Details';
    }

    public function headings(): array
    {
        return ['Category', 'Expense Account', 'Transactions', 'Total Amount', 'Payment Account'];
    }

    public function collection(): Collection
    {
        return collect($this->rows)->map(fn (array $row) => [
            $row['category'] ?? '',
            $row['account_name'] ?? '',
            $row['transaction_count'] ?? 0,
            $row['total_amount'] ?? 0,
            $row['payment_account'] ?? '',
        ]);
    }
}
