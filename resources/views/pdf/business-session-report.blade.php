<!DOCTYPE html>
<html lang="en">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Business Session {{ $report['session']['session_number'] ?? '' }}</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #1e293b; line-height: 1.4; }
        h1 { font-size: 16px; margin-bottom: 4px; }
        h2 { font-size: 12px; margin: 16px 0 8px; color: #0f172a; }
        .meta { margin-bottom: 12px; }
        .meta td { padding: 2px 12px 2px 0; }
        table.data { width: 100%; border-collapse: collapse; margin-bottom: 12px; }
        table.data th, table.data td { border: 1px solid #cbd5e1; padding: 4px 6px; text-align: left; }
        table.data th { background: #f1f5f9; font-weight: bold; }
        .text-right { text-align: right; }
    </style>
</head>
<body>
@php
    $session = $report['session'] ?? [];
    $closing = $report['closing_summary'] ?? [];
@endphp

<h1>Business Session Closing Report</h1>
<p><strong>{{ $session['session_number'] ?? '' }}</strong> — {{ $session['branch_name'] ?? '' }}</p>

<table class="meta">
    <tr><td><strong>Date</strong></td><td>{{ $session['session_date'] ?? '' }}</td><td><strong>Started By</strong></td><td>{{ $session['started_by'] ?? '' }}</td></tr>
    <tr><td><strong>Start</strong></td><td>{{ $session['started_at'] ?? '' }}</td><td><strong>Close</strong></td><td>{{ $session['closed_at'] ?? '—' }}</td></tr>
    <tr><td><strong>Duration</strong></td><td>{{ $session['duration'] ?? '' }}</td><td><strong>Status</strong></td><td>{{ $session['status'] ?? '' }}</td></tr>
</table>

<h2>Closing Summary</h2>
<table class="data">
    <tr>
        <th>Opening</th><th>Receipts</th><th>Payments</th><th>Income</th><th>Expenses</th><th>Transfer In</th><th>Transfer Out</th><th>Closing</th>
    </tr>
    <tr class="text-right">
        <td class="text-right">{{ number_format($closing['total_opening_balance'] ?? 0, 2) }}</td>
        <td class="text-right">{{ number_format($closing['total_receipts'] ?? 0, 2) }}</td>
        <td class="text-right">{{ number_format($closing['total_payments'] ?? 0, 2) }}</td>
        <td class="text-right">{{ number_format($closing['total_income'] ?? 0, 2) }}</td>
        <td class="text-right">{{ number_format($closing['total_expenses'] ?? 0, 2) }}</td>
        <td class="text-right">{{ number_format($closing['total_transfer_in'] ?? 0, 2) }}</td>
        <td class="text-right">{{ number_format($closing['total_transfer_out'] ?? 0, 2) }}</td>
        <td class="text-right">{{ number_format($closing['total_closing_balance'] ?? 0, 2) }}</td>
    </tr>
</table>

<h2>Account Balances</h2>
<table class="data">
    <tr>
        <th>Account</th><th>Type</th><th class="text-right">Opening</th><th class="text-right">Received</th><th class="text-right">Paid</th><th class="text-right">Closing</th>
    </tr>
    @foreach ($report['account_balances'] ?? [] as $row)
        <tr>
            <td>{{ $row['account_name'] ?? '' }}</td>
            <td>{{ $row['account_type'] ?? '' }}</td>
            <td class="text-right">{{ number_format($row['opening_balance'] ?? 0, 2) }}</td>
            <td class="text-right">{{ number_format($row['total_received'] ?? 0, 2) }}</td>
            <td class="text-right">{{ number_format($row['total_paid'] ?? 0, 2) }}</td>
            <td class="text-right">{{ number_format($row['closing_balance'] ?? 0, 2) }}</td>
        </tr>
    @endforeach
</table>
</body>
</html>
