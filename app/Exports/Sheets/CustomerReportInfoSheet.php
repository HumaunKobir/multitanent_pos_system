<?php

namespace App\Exports\Sheets;

use App\Exports\Support\CustomerReportSheetStyles;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;

class CustomerReportInfoSheet implements FromArray, ShouldAutoSize, WithEvents, WithTitle
{
    private const HEADER_ROW = 5;

    private const DATA_START_ROW = 6;

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
        $name = $this->customer['name'] ?? '—';

        return [
            ['Customer Report'],
            [CustomerReportSheetStyles::subtitle($name !== '—' ? (string) $name : 'Unknown')],
            ['', ''],
            ['Customer Details'],
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

    /**
     * @return array<class-string, callable>
     */
    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event): void {
                $sheet = $event->sheet->getDelegate();
                $customerName = (string) ($this->customer['name'] ?? 'Customer');

                CustomerReportSheetStyles::applyReportHeader(
                    $sheet,
                    'Customer Report',
                    CustomerReportSheetStyles::subtitle($customerName),
                    2,
                );

                CustomerReportSheetStyles::applySectionTitle($sheet, 4, 'Customer Details', 2);

                $dataEndRow = self::DATA_START_ROW + 9;

                CustomerReportSheetStyles::applyLabelValueTable(
                    $sheet,
                    self::HEADER_ROW,
                    self::DATA_START_ROW,
                    $dataEndRow,
                    currencyRows: [13, 14],
                );
            },
        ];
    }
}
