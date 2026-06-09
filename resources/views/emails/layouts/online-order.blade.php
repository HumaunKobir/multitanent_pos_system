<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title ?? $siteName }}</title>
</head>
<body style="margin:0;padding:0;background-color:#f1f5f9;font-family:Arial,Helvetica,sans-serif;color:#1e293b;">
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background-color:#f1f5f9;padding:24px 12px;">
    <tr>
        <td align="center">
            <table role="presentation" width="600" cellspacing="0" cellpadding="0" style="max-width:600px;width:100%;background:#ffffff;border-radius:12px;overflow:hidden;box-shadow:0 4px 24px rgba(15,23,42,0.08);">
                <tr>
                    <td style="background:linear-gradient(135deg,#0f172a 0%,#1e3a5f 100%);padding:28px 32px;text-align:center;">
                        @if(!empty($logoUrl))
                            <img src="{{ $logoUrl }}" alt="{{ $siteName }}" style="max-height:56px;max-width:200px;margin-bottom:12px;">
                        @else
                            <div style="font-size:11px;letter-spacing:2px;text-transform:uppercase;color:#94a3b8;margin-bottom:8px;">{{ $siteName }}</div>
                        @endif
                        <h1 style="margin:0;font-size:22px;line-height:1.3;color:#ffffff;font-weight:700;">{{ $heading }}</h1>
                        @if(!empty($subheading))
                            <p style="margin:10px 0 0;font-size:14px;color:#cbd5e1;">{{ $subheading }}</p>
                        @endif
                    </td>
                </tr>
                <tr>
                    <td style="padding:32px;">
                        {{ $slot }}
                    </td>
                </tr>
                <tr>
                    <td style="background:#f8fafc;padding:20px 32px;border-top:1px solid #e2e8f0;text-align:center;">
                        <p style="margin:0 0 6px;font-size:12px;color:#64748b;">Need help? Contact us</p>
                        @if(!empty($supportPhone))
                            <p style="margin:0 0 4px;font-size:12px;color:#475569;">Phone: {{ $supportPhone }}</p>
                        @endif
                        @if(!empty($supportEmail))
                            <p style="margin:0 0 4px;font-size:12px;color:#475569;">Email: {{ $supportEmail }}</p>
                        @endif
                        @if(!empty($supportTime))
                            <p style="margin:0;font-size:11px;color:#94a3b8;">Support hours: {{ $supportTime }}</p>
                        @endif
                    </td>
                </tr>
            </table>
            <p style="margin:16px 0 0;font-size:11px;color:#94a3b8;text-align:center;">&copy; {{ date('Y') }} {{ $siteName }}. All rights reserved.</p>
        </td>
    </tr>
</table>
</body>
</html>
