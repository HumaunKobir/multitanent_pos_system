@php
    $heading = $isCombined ? 'Order Confirmed & Payment Received!' : 'Payment Received!';
    $subheading = $isCombined
        ? 'Thank you for your order and payment.'
        : 'Your payment has been confirmed.';
@endphp

@component('emails.layouts.online-order', compact('siteName', 'logoUrl', 'supportPhone', 'supportEmail', 'supportTime', 'heading', 'subheading'))
    <p style="margin:0 0 16px;font-size:15px;line-height:1.6;color:#334155;">
        Hello <strong>{{ $order->name }}</strong>,
    </p>
    <p style="margin:0 0 20px;font-size:14px;line-height:1.6;color:#475569;">
        @if($isCombined)
            Thank you for shopping with us! We have received your order and payment successfully. Your order is now confirmed and will be processed shortly.
        @else
            Great news! We have successfully received your payment. Your order will continue to be processed for delivery.
        @endif
    </p>

    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#ecfdf5;border:1px solid #a7f3d0;border-radius:8px;margin-bottom:20px;">
        <tr>
            <td style="padding:16px 18px;text-align:center;">
                <div style="font-size:12px;text-transform:uppercase;letter-spacing:1px;color:#059669;margin-bottom:4px;">Payment Status</div>
                <div style="font-size:20px;font-weight:700;color:#047857;">Paid</div>
            </td>
        </tr>
    </table>

    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;margin-bottom:20px;">
        <tr>
            <td style="padding:16px 18px;">
                <table role="presentation" width="100%" cellspacing="0" cellpadding="0">
                    <tr>
                        <td style="padding:4px 0;font-size:13px;color:#64748b;">Invoice</td>
                        <td align="right" style="padding:4px 0;font-size:13px;font-weight:700;color:#0f172a;">{{ $invoiceNumber }}</td>
                    </tr>
                    @if($order->transaction_id)
                        <tr>
                            <td style="padding:4px 0;font-size:13px;color:#64748b;">Transaction ID</td>
                            <td align="right" style="padding:4px 0;font-size:12px;font-family:monospace;color:#0f172a;">{{ $order->transaction_id }}</td>
                        </tr>
                    @endif
                    <tr>
                        <td style="padding:4px 0;font-size:13px;color:#64748b;">Payment Method</td>
                        <td align="right" style="padding:4px 0;font-size:13px;color:#0f172a;">{{ $order->paymentMethodLabel() }}</td>
                    </tr>
                    <tr>
                        <td style="padding:8px 0 4px;font-size:14px;color:#64748b;font-weight:700;border-top:1px solid #e2e8f0;">Amount Paid</td>
                        <td align="right" style="padding:8px 0 4px;font-size:18px;font-weight:700;color:#0f172a;border-top:1px solid #e2e8f0;">৳{{ number_format((float) $order->total, 2) }}</td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    <p style="margin:0 0 12px;font-size:13px;line-height:1.6;color:#475569;">
        <strong>Items ordered:</strong>
    </p>
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border:1px solid #e2e8f0;border-radius:8px;overflow:hidden;margin-bottom:16px;">
        @foreach($order->products as $item)
            <tr>
                <td style="padding:10px 14px;border-bottom:1px solid #e2e8f0;font-size:13px;color:#334155;">
                    {{ $item->name }} <span style="color:#94a3b8;">× {{ $item->quantity }}</span>
                </td>
                <td align="right" style="padding:10px 14px;border-bottom:1px solid #e2e8f0;font-size:13px;font-weight:600;color:#0f172a;white-space:nowrap;">
                    ৳{{ number_format((float) $item->total_price, 2) }}
                </td>
            </tr>
        @endforeach
    </table>

@endcomponent
