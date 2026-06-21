@php
    $heading = $siteName;
    $subheading = 'Newsletter update from our team';
@endphp

@component('emails.layouts.online-order', compact('siteName', 'logoUrl', 'supportPhone', 'supportEmail', 'supportTime', 'heading', 'subheading'))
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:10px;margin-bottom:8px;">
        <tr>
            <td style="padding:20px 22px;">
                <div style="font-size:14px;line-height:1.75;color:#334155;white-space:pre-wrap;">{!! nl2br(e($mailBody)) !!}</div>
            </td>
        </tr>
    </table>

    <p style="margin:16px 0 0;font-size:12px;line-height:1.6;color:#94a3b8;text-align:center;">
        You are receiving this email because you subscribed to our newsletter.
    </p>
@endcomponent
