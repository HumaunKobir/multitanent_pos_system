<?php

use App\Exports\CustomerReportExport;
use App\Exports\CustomersBulkReportExport;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

uses(TestCase::class);

/**
 * @return array<string, mixed>
 */
function customerReportSampleData(): array
{
    return [
        'customer' => [
            'name' => 'Walk in Customer',
            'phone' => '01300000000',
            'email' => null,
            'address' => null,
            'status' => 'Active',
            'registration_type' => 'Offline',
            'coin_balance' => 0,
            'due_balance' => 1500,
            'is_default' => 'Yes',
        ],
        'sales' => [[
            'date' => '2026-01-01',
            'invoice_number' => 'INV-001',
            'gross_amount' => 2000,
            'discount' => 0,
            'vat' => 0,
            'net_amount' => 2000,
            'paid_amount' => 500,
            'due_amount' => 1500,
            'comment' => 'Sample sale',
        ]],
        'collections' => [[
            'date' => '2026-01-15',
            'invoice_number' => 'INV-001',
            'amount' => 300,
            'comment' => 'Partial collection',
            'created_by' => 'Admin User',
        ]],
        'due_sales' => [[
            'date' => '2026-01-01',
            'invoice_number' => 'INV-001',
            'net_amount' => 2000,
            'paid_amount' => 500,
            'due_amount' => 1500,
        ]],
        'totals' => [
            'sales_net' => 2000,
            'sales_paid' => 500,
            'sales_due' => 1500,
            'collections' => 300,
            'current_due' => 1500,
            'account_balance' => 1500,
        ],
        'date_from' => null,
        'date_to' => null,
    ];
}

test('customer report export generates a styled multi-sheet workbook', function () {
    $filename = 'customer-report-export-test.xlsx';

    Storage::disk('local')->delete($filename);

    Excel::store(new CustomerReportExport(customerReportSampleData()), $filename, 'local');

    $path = Storage::disk('local')->path($filename);

    expect(file_exists($path))->toBeTrue();

    $spreadsheet = IOFactory::load($path);

    expect($spreadsheet->getSheetCount())->toBe(4);
    expect($spreadsheet->getSheetNames())->toBe([
        'Customer Info',
        'Sales',
        'Due Collections',
        'Current Due',
    ]);

    $infoSheet = $spreadsheet->getSheetByName('Customer Info');
    expect($infoSheet->getCell('A1')->getValue())->toBe('Customer Report');
    expect($infoSheet->getCell('A4')->getValue())->toBe('Customer Details');
    expect($infoSheet->getCell('A5')->getValue())->toBe('Field');
    expect($infoSheet->getStyle('A5:B5')->getFont()->getBold())->toBeTrue();

    $salesSheet = $spreadsheet->getSheetByName('Sales');
    expect($salesSheet->getCell('A1')->getValue())->toBe('Sales History');
    expect($salesSheet->getCell('A4')->getValue())->toBe('Date');
    expect($salesSheet->getStyle('A4:I4')->getFill()->getStartColor()->getRGB())->toBe('1F4E79');

    Storage::disk('local')->delete($filename);
});

test('customers bulk report export generates a styled multi-sheet workbook', function () {
    $filename = 'customers-bulk-report-export-test.xlsx';

    Storage::disk('local')->delete($filename);

    $data = [
        'customers' => [[
            'name' => 'Customer A',
            'phone' => '01300000001',
            'email' => null,
            'address' => null,
            'status' => 'Active',
            'coin_balance' => 0,
            'due_balance' => 500,
        ]],
        'sales' => [[
            'customer' => 'Customer A',
            'phone' => '01300000001',
            'date' => '2026-01-01',
            'invoice_number' => 'INV-001',
            'gross_amount' => 1000,
            'discount' => 0,
            'vat' => 0,
            'net_amount' => 1000,
            'paid_amount' => 500,
            'due_amount' => 500,
            'comment' => null,
        ]],
        'collections' => [],
        'due_sales' => [[
            'customer' => 'Customer A',
            'phone' => '01300000001',
            'date' => '2026-01-01',
            'invoice_number' => 'INV-001',
            'net_amount' => 1000,
            'paid_amount' => 500,
            'due_amount' => 500,
        ]],
        'totals' => [
            'sales_net' => 1000,
            'sales_paid' => 500,
            'sales_due' => 500,
            'collections' => 0,
            'current_due' => 500,
            'account_balance' => 500,
        ],
        'date_from' => '2026-01-01',
        'date_to' => '2026-03-31',
    ];

    Excel::store(new CustomersBulkReportExport($data), $filename, 'local');

    $path = Storage::disk('local')->path($filename);

    expect(file_exists($path))->toBeTrue();

    $spreadsheet = IOFactory::load($path);

    expect($spreadsheet->getSheetCount())->toBe(4);
    expect($spreadsheet->getSheetNames())->toBe([
        'Customers',
        'Sales',
        'Due Collections',
        'Current Due',
    ]);

    Storage::disk('local')->delete($filename);
});
