<?php

namespace App\Exports;

use App\Exports\Sheets\CustomerReportCollectionsSheet;
use App\Exports\Sheets\CustomerReportCurrentDueSheet;
use App\Exports\Sheets\CustomerReportInfoSheet;
use App\Exports\Sheets\CustomerReportSalesSheet;
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
        return [
            new CustomerReportInfoSheet($this->data['customer']),
            new CustomerReportSalesSheet($this->data['sales'], $this->data['totals']),
            new CustomerReportCollectionsSheet($this->data['collections'], $this->data['totals']),
            new CustomerReportCurrentDueSheet($this->data['due_sales'], $this->data['totals']),
        ];
    }
}
