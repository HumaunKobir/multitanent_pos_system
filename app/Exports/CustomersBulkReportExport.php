<?php

namespace App\Exports;

use App\Exports\Sheets\CustomerReportBulkCollectionsSheet;
use App\Exports\Sheets\CustomerReportBulkCurrentDueSheet;
use App\Exports\Sheets\CustomerReportBulkSalesSheet;
use App\Exports\Sheets\CustomerReportBulkSummarySheet;
use App\Exports\Support\CustomerReportSheetStyles;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class CustomersBulkReportExport implements WithMultipleSheets
{
    /**
     * @param  array{
     *     customers: list<array<string, mixed>>,
     *     sales: list<array<string, mixed>>,
     *     collections: list<array<string, mixed>>,
     *     due_sales: list<array<string, mixed>>,
     *     totals: array<string, float>,
     *     date_from: string|null,
     *     date_to: string|null
     * }  $data
     */
    public function __construct(private array $data) {}

    /**
     * @return list<object>
     */
    public function sheets(): array
    {
        $subtitle = CustomerReportSheetStyles::bulkSubtitle(
            count($this->data['customers']),
            $this->data['date_from'] ?? null,
            $this->data['date_to'] ?? null,
        );

        return [
            new CustomerReportBulkSummarySheet($this->data['customers'], $subtitle),
            new CustomerReportBulkSalesSheet($this->data['sales'], $this->data['totals'], $subtitle),
            new CustomerReportBulkCollectionsSheet($this->data['collections'], $this->data['totals'], $subtitle),
            new CustomerReportBulkCurrentDueSheet($this->data['due_sales'], $this->data['totals'], $subtitle),
        ];
    }
}
