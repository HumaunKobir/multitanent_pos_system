<!DOCTYPE html>
<html lang="en">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>{{ $invoiceNumber }}</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 11px;
            color: #1e293b;
            line-height: 1.45;
        }

        .money {
            white-space: nowrap;
        }

        .page { padding: 0; }

        .accent-bar {
            height: 6px;
            background: #0f172a;
            margin-bottom: 22px;
        }

        .content { padding: 0 34px 30px; }

        .header {
            display: table;
            width: 100%;
            margin-bottom: 22px;
        }

        .header-left, .header-right {
            display: table-cell;
            vertical-align: top;
        }

        .header-right {
            text-align: right;
            width: 42%;
        }

        .logo { max-height: 58px; max-width: 190px; margin-bottom: 10px; }

        .brand-name {
            font-size: 24px;
            font-weight: bold;
            color: #0f172a;
            letter-spacing: 0.3px;
            margin-bottom: 6px;
        }

        .brand-meta {
            font-size: 10px;
            color: #64748b;
            margin-top: 3px;
            line-height: 1.6;
        }

        .invoice-card {
            display: inline-block;
            border: 1px solid #e2e8f0;
            border-top: 4px solid #0f172a;
            padding: 14px 16px;
            min-width: 230px;
            text-align: left;
            background: #f8fafc;
        }

        .invoice-badge {
            display: inline-block;
            background: #0f172a;
            color: #fff;
            padding: 4px 10px;
            font-size: 9px;
            font-weight: bold;
            letter-spacing: 1.2px;
            margin-bottom: 8px;
        }

        .invoice-title {
            font-size: 22px;
            font-weight: bold;
            color: #0f172a;
            margin-bottom: 8px;
        }

        .invoice-meta {
            font-size: 10px;
            color: #475569;
            line-height: 1.7;
        }

        .badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 10px;
            font-size: 9px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.4px;
        }

        .badge-paid { background: #dcfce7; color: #166534; }
        .badge-pending { background: #ffedd5; color: #9a3412; }

        .grid {
            display: table;
            width: 100%;
            margin-bottom: 20px;
        }

        .grid-col {
            display: table-cell;
            width: 50%;
            vertical-align: top;
            padding-right: 10px;
        }

        .grid-col:last-child { padding-right: 0; padding-left: 10px; }

        .section-title {
            font-size: 9px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 1.2px;
            color: #64748b;
            margin-bottom: 7px;
        }

        .box {
            border: 1px solid #e2e8f0;
            padding: 14px 15px;
            background: #fff;
            min-height: 88px;
        }

        .box p { margin-bottom: 4px; font-size: 11px; }
        .box p strong { color: #0f172a; }

        table.items {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 0;
            border: 1px solid #e2e8f0;
            table-layout: fixed;
        }

        table.items th {
            background: #0f172a;
            color: #fff;
            font-size: 9px;
            text-transform: uppercase;
            letter-spacing: 0.6px;
            padding: 10px 8px;
            text-align: left;
        }

        table.items td {
            padding: 10px 8px;
            border-bottom: 1px solid #eef2f7;
            vertical-align: middle;
            font-size: 11px;
            word-wrap: break-word;
        }

        table.items tbody tr:nth-child(even) td { background: #f8fafc; }

        table.items tfoot td {
            padding: 9px 8px;
            border-top: 1px solid #eef2f7;
            font-size: 11px;
            background: #fff;
        }

        table.items tfoot tr.grand td {
            background: #0f172a;
            color: #fff;
            font-size: 13px;
            font-weight: bold;
            border-top: none;
        }

        .col-index { width: 5%; }
        .col-product { width: 36%; }
        .col-sku { width: 14%; }
        .col-price { width: 15%; }
        .col-qty { width: 10%; }
        .col-total { width: 20%; }

        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .text-left { text-align: left; }

        .label-cell {
            text-align: right;
            padding-right: 12px;
            color: #475569;
        }

        .amount-cell {
            text-align: right;
            padding-right: 8px;
            padding-left: 4px;
        }

        .footer {
            margin-top: 24px;
            padding-top: 14px;
            border-top: 1px dashed #cbd5e1;
            text-align: center;
            font-size: 10px;
            color: #94a3b8;
        }

        .footer strong { color: #475569; display: block; margin-bottom: 3px; }
    </style>
</head>
<body>
@php use App\Support\MoneyFormat; @endphp
<div class="page">
    <div class="accent-bar"></div>

    <div class="content">
        <div class="header">
            <div class="header-left">
                @if($company['logoDataUri'])
                    <img src="{{ $company['logoDataUri'] }}" alt="{{ $company['name'] }}" class="logo">
                @else
                    <div class="brand-name">{{ $company['name'] }}</div>
                @endif
                <div class="brand-meta">
                    @if($company['phone'])<div>{{ $company['phone'] }}</div>@endif
                    @if($company['email'])<div>{{ $company['email'] }}</div>@endif
                    @if($company['address'])<div>{{ $company['address'] }}</div>@endif
                </div>
            </div>
            <div class="header-right">
                <div class="invoice-card">
                    <div class="invoice-badge">ORDER INVOICE</div>
                    <div class="invoice-title">{{ $invoiceNumber }}</div>
                    <div class="invoice-meta">
                        Order #{{ $order->id }}<br>
                        Date: {{ $order->created_at?->format('d M Y, h:i A') }}<br>
                        @if($order->transaction_id)
                            Transaction: {{ $order->transaction_id }}<br>
                        @endif
                        Payment: {{ $order->paymentMethodLabel() }}<br>
                        Order Status: {{ $order->status->label() }}<br>
                        Payment Status:
                        @if(strtolower($order->payment_status) === 'paid')
                            <span class="badge badge-paid">Paid</span>
                        @else
                            <span class="badge badge-pending">{{ $order->payment_status }}</span>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <div class="grid">
            <div class="grid-col">
                <div class="section-title">Bill To</div>
                <div class="box">
                    <p><strong>{{ $order->name }}</strong></p>
                    <p>{{ $order->phone }}</p>
                    @if($order->email)
                        <p>{{ $order->email }}</p>
                    @endif
                    <p>{{ $order->address }}</p>
                </div>
            </div>
            <div class="grid-col">
                <div class="section-title">Order Summary</div>
                <div class="box">
                    <p><strong>Order ID:</strong> #{{ $order->id }}</p>
                    <p><strong>Invoice:</strong> {{ $invoiceNumber }}</p>
                    <p><strong>Payment Method:</strong> {{ $order->paymentMethodLabel() }}</p>
                    <p><strong>Order Status:</strong> {{ $order->status->label() }}</p>
                    <p><strong>Payment Status:</strong>
                        @if(strtolower($order->payment_status) === 'paid')
                            <span class="badge badge-paid">Paid</span>
                        @else
                            <span class="badge badge-pending">{{ $order->payment_status }}</span>
                        @endif
                    </p>
                </div>
            </div>
        </div>

        <table class="items">
            <thead>
                <tr>
                    <th class="col-index text-center">#</th>
                    <th class="col-product text-left">Product</th>
                    <th class="col-sku text-left">SKU</th>
                    <th class="col-price text-right">Unit Price</th>
                    <th class="col-qty text-center">Qty</th>
                    <th class="col-total text-right">Total</th>
                </tr>
            </thead>
            <tbody>
                @foreach($order->products as $index => $item)
                    <tr>
                        <td class="text-center">{{ $index + 1 }}</td>
                        <td class="text-left">{{ $item->name }}</td>
                        <td class="text-left">{{ $item->sku ?: '—' }}</td>
                        <td class="amount-cell money">{!! MoneyFormat::bdt($item->price) !!}</td>
                        <td class="text-center">{{ $item->quantity }}</td>
                        <td class="amount-cell money">{!! MoneyFormat::bdt($item->total_price) !!}</td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="5" class="label-cell">Subtotal</td>
                    <td class="amount-cell money">{!! MoneyFormat::bdt($order->subtotal) !!}</td>
                </tr>
                <tr>
                    <td colspan="5" class="label-cell">Delivery Charge</td>
                    <td class="amount-cell money">{!! MoneyFormat::bdt($order->delivery_charge) !!}</td>
                </tr>
                <tr class="grand">
                    <td colspan="5" class="label-cell" style="color:#fff;">Grand Total</td>
                    <td class="amount-cell money">{!! MoneyFormat::bdt($order->total) !!}</td>
                </tr>
            </tfoot>
        </table>

        <div class="footer">
            <strong>Thank you for shopping with {{ $company['name'] }}!</strong>
            This is a computer-generated invoice and does not require a signature.
        </div>
    </div>
</div>
</body>
</html>
