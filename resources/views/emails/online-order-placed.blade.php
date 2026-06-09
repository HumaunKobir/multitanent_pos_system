@php
    $heading = 'Order Confirmed!';
    $subheading = 'Thank you for your order. Here is a quick summary of your purchase.';
@endphp

@component('emails.layouts.online-order', compact('siteName', 'logoUrl', 'supportPhone', 'supportEmail', 'supportTime', 'heading', 'subheading'))
    <p style="margin:0 0 16px;font-size:15px;line-height:1.6;color:#334155;">
        Hello <strong>{{ $order->name }}</strong>,
    </p>
    <p style="margin:0 0 20px;font-size:14px;line-height:1.6;color:#475569;">
        We have received your order and it is now being processed. Here is a quick summary:
    </p>

    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;margin-bottom:20px;">
        <tr>
            <td style="padding:16px 18px;">
                <table role="presentation" width="100%" cellspacing="0" cellpadding="0">
                    <tr>
                        <td style="padding:4px 0;font-size:13px;color:#64748b;">Invoice</td>
                        <td align="right" style="padding:4px 0;font-size:13px;font-weight:700;color:#0f172a;">{{ $invoiceNumber }}</td>
                    </tr>
                    <tr>
                        <td style="padding:4px 0;font-size:13px;color:#64748b;">Payment Method</td>
                        <td align="right" style="padding:4px 0;font-size:13px;color:#0f172a;">{{ $order->paymentMethodLabel() }}</td>
                    </tr>
                    <tr>
                        <td style="padding:4px 0;font-size:13px;color:#64748b;">Payment Status</td>
                        <td align="right" style="padding:4px 0;font-size:13px;color:#d97706;font-weight:700;">{{ $order->payment_status }}</td>
                    </tr>
                    <tr>
                        <td style="padding:8px 0 4px;font-size:14px;color:#64748b;font-weight:700;border-top:1px solid #e2e8f0;">Total</td>
                        <td align="right" style="padding:8px 0 4px;font-size:16px;font-weight:700;color:#0f172a;border-top:1px solid #e2e8f0;">৳{{ number_format((float) $order->total, 2) }}</td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    <p style="margin:0 0 12px;font-size:13px;line-height:1.6;color:#475569;">
        <strong>Items ordered:</strong>
    </p>
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border:1px solid #e2e8f0;border-radius:10px;overflow:hidden;margin-bottom:16px;">
        <tr>
            <td style="background:#0f172a;padding:9px 14px;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.8px;color:#94a3b8;border-bottom:1px solid #1e293b;">Item</td>
            <td align="right" style="background:#0f172a;padding:9px 14px;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.8px;color:#94a3b8;border-bottom:1px solid #1e293b;">Total</td>
        </tr>
        @foreach($order->products as $item)
            <tr>
                <td style="padding:11px 14px;font-size:13px;color:#334155;border-bottom:1px solid #f1f5f9;vertical-align:top;">
                    <div style="font-weight:600;color:#0f172a;">{{ $item->name }}</div>
                    <div style="font-size:11px;color:#94a3b8;margin-top:2px;">Quantity: {{ $item->quantity }}</div>
                </td>
                <td align="right" style="padding:11px 14px;font-size:14px;font-weight:700;color:#0f172a;white-space:nowrap;border-bottom:1px solid #f1f5f9;vertical-align:middle;">
                    ৳{{ number_format((float) $item->total_price, 2) }}
                </td>
            </tr>
        @endforeach
        <tr>
            <td style="padding:11px 14px;font-size:12px;color:#64748b;border-top:2px solid #e2e8f0;">Subtotal</td>
            <td align="right" style="padding:11px 14px;font-size:14px;font-weight:700;color:#0f172a;white-space:nowrap;border-top:2px solid #e2e8f0;">৳{{ number_format((float) $order->total, 2) }}</td>
        </tr>
    </table>

    <p style="margin:0 0 12px;font-size:13px;line-height:1.6;color:#475569;">
        <strong>Delivery address:</strong><br>
        {{ $order->address }}
    </p>
@endcomponent
