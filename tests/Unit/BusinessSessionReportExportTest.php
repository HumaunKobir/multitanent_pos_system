<?php

use App\Exports\BusinessSessionReportExport;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

uses(TestCase::class);

/**
 * @return array<string, mixed>
 */
function businessSessionReportSampleData(): array
{
    return [
        'session' => [
            'session_number' => 'BR1-20260701-001',
            'session_date' => '2026-07-01',
            'branch_name' => 'Main Branch',
            'started_by' => 'Super Admin',
            'started_at' => '2026-07-01 1:22 PM',
            'closed_at' => '2026-07-01 1:29 PM',
            'closed_by' => 'Super Admin',
            'duration' => '7m',
            'status' => 'Closed',
            'opening_method' => 'Manual',
        ],
        'account_balances' => [[
            'account_name' => 'Cash in Hand',
            'account_type' => 'Asset',
            'opening_balance' => 20000000,
            'total_debit' => 0,
            'total_credit' => 0,
            'total_received' => 0,
            'total_paid' => 0,
            'total_transfer_in' => 0,
            'total_transfer_out' => 0,
            'closing_balance' => 20000000,
        ]],
        'transactions' => [[
            'date' => '2026-07-01',
            'time' => '1:22 PM',
            'reference' => 'VCH-001',
            'type' => 'Income',
            'source_account' => '1001 — Cash in Hand',
            'destination_account' => '4001 — Sales Income',
            'description' => 'Sample transaction',
            'debit' => 500,
            'credit' => 500,
            'created_by' => 'Super Admin',
            'branch' => 'Main Branch',
            'approval_status' => 'Approved',
            'is_deleted' => false,
        ]],
        'income_summary' => [[
            'category' => 'Sales Income',
            'account_name' => 'Sales Income',
            'transaction_count' => 1,
            'total_amount' => 500,
        ]],
        'expense_summary' => [[
            'category' => 'Office Expense',
            'account_name' => 'Office Expense',
            'transaction_count' => 1,
            'total_amount' => 200,
            'payment_account' => 'Cash in Hand',
        ]],
        'transfers' => [[
            'date' => '2026-07-01',
            'time' => '1:25 PM',
            'reference' => 'CVR-001',
            'from_account' => 'Cash in Hand',
            'to_account' => 'Bank Account',
            'amount' => 1000,
            'method' => 'Contra',
            'created_by' => 'Super Admin',
            'remarks' => 'Sample transfer',
        ]],
        'closing_summary' => [
            'total_opening_balance' => 20000000,
            'total_receipts' => 0,
            'total_payments' => 0,
            'total_income' => 500,
            'total_expenses' => 200,
            'total_transfer_in' => 0,
            'total_transfer_out' => 0,
            'total_closing_balance' => 20000000,
        ],
    ];
}

test('business session report export generates a styled multi-sheet workbook', function () {
    $filename = 'business-session-report-export-test.xlsx';

    Storage::disk('local')->delete($filename);

    Excel::store(new BusinessSessionReportExport(businessSessionReportSampleData()), $filename, 'local');

    $path = Storage::disk('local')->path($filename);

    expect(file_exists($path))->toBeTrue();

    $spreadsheet = IOFactory::load($path);

    expect($spreadsheet->getSheetCount())->toBe(6);
    expect($spreadsheet->getSheetNames())->toBe([
        'Session Summary',
        'Account Balances',
        'All Transactions',
        'Income Details',
        'Expense Details',
        'Balance Transfers',
    ]);

    $summarySheet = $spreadsheet->getSheetByName('Session Summary');
    expect($summarySheet->getCell('A1')->getValue())->toBe('Business Session Closing Report');
    expect($summarySheet->getCell('A4')->getValue())->toBe('Session Information');
    expect($summarySheet->getCell('B6')->getValue())->toBe('BR1-20260701-001');
    expect($summarySheet->getCell('B7')->getValue())->toBe('01 July, 2026');
    expect($summarySheet->getCell('A17')->getValue())->toBe('Financial Summary');
    expect($summarySheet->getStyle('A5:B5')->getFont()->getBold())->toBeTrue();

    $balancesSheet = $spreadsheet->getSheetByName('Account Balances');
    expect($balancesSheet->getCell('A1')->getValue())->toBe('Account Balances');
    expect($balancesSheet->getCell('A4')->getValue())->toBe('Account');
    expect($balancesSheet->getStyle('A4:J4')->getFill()->getStartColor()->getRGB())->toBe('1F4E79');
    expect($balancesSheet->getCell('A6')->getValue())->toBe('Totals');

    $transactionsSheet = $spreadsheet->getSheetByName('All Transactions');
    expect($transactionsSheet->getCell('A5')->getValue())->toBe('01 July, 2026');

    Storage::disk('local')->delete($filename);
});
