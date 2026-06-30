<?php

namespace App\Exports\Sheets;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithTitle;

class CustomerReportInfoSheet implements FromArray, ShouldAutoSize, WithTitle
{
    /**
     * @param  array<string, mixed>  $customer
     */
    public function __construct(private array $customer) {}

    public function title(): string
    {
        return 'Customer Info';
    }

    /**
     * @return list<list<string|float|null>>
     */
    public function array(): array
    {
        return [
            ['Field', 'Value'],
            ['Name', $this->customer['name'] ?? '—'],
            ['Phone', $this->customer['phone'] ?? '—'],
            ['Email', $this->customer['email'] ?? '—'],
            ['Address', $this->customer['address'] ?? '—'],
            ['Branch', $this->customer['branch'] ?? '—'],
            ['Status', $this->customer['status'] ?? '—'],
            ['Registration Type', $this->customer['registration_type'] ?? '—'],
            ['Coin Balance', $this->customer['coin_balance'] ?? 0],
            ['Current Due Balance', $this->customer['due_balance'] ?? 0],
            ['Is Default', $this->customer['is_default'] ?? 'No'],
        ];
    }
}
