@php
    $heading = 'Order Confirmed!';
    $subheading = 'Thank you for your order. Your invoice is attached as a PDF.';
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
                        <td style="padding:4px 0;font-size:13px;color:#64748b;">Order ID</td>
                        <td align="right" style="padding:4px 0;font-size:13px;font-weight:700;color:#0f172a;">#{{ $order->id }}</td>
                    </tr>
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
        <strong>Delivery address:</strong><br>
        {{ $order->address }}
    </p>

    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="margin-top:8px;">
        <tr>
            <td style="background:#fff7ed;border:1px solid #fed7aa;border-radius:8px;padding:14px 16px;">
                <p style="margin:0;font-size:13px;line-height:1.5;color:#9a3412;">
                    Your invoice PDF is attached to this email. Payment will be collected on delivery for Cash on Delivery orders.
                </p>
            </td>
        </tr>
    </table>
@endcomponent
