<?php

namespace App\Exports;

use App\Exports\Sheets\CustomerReportCollectionsSheet;
use App\Exports\Sheets\CustomerReportCurrentDueSheet;
use App\Exports\Sheets\CustomerReportInfoSheet;
use App\Exports\Sheets\CustomerReportSalesSheet;
use App\Exports\Support\CustomerReportSheetStyles;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class CustomerReportExport implements WithMultipleSheets
{
    /**
     * @param  array{
     *     customer: array<string, mixed>,
     *     sales: list<array<string, mixed>>,
     *     collections: list<array<string, mixed>>,
     *     due_sales: list<array<string, mixed>>,
     *     totals: array<string, float>
     * }  $data
     */
    public function __construct(private array $data) {}

    /**
     * @return list<object>
     */
    public function sheets(): array
    {
        $customerName = (string) ($this->data['customer']['name'] ?? 'Customer');
        $dateFrom = $this->data['date_from'] ?? null;
        $dateTo = $this->data['date_to'] ?? null;
        $subtitle = CustomerReportSheetStyles::subtitle($customerName, $dateFrom, $dateTo);

        return [
            new CustomerReportInfoSheet($this->data['customer'], $subtitle),
            new CustomerReportSalesSheet($this->data['sales'], $this->data['totals'], $subtitle),
            new CustomerReportCollectionsSheet($this->data['collections'], $this->data['totals'], $subtitle),
            new CustomerReportCurrentDueSheet($this->data['due_sales'], $this->data['totals'], $subtitle),
        ];
    }
}
