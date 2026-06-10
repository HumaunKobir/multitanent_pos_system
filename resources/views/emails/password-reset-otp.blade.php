@php
    $heading = 'Password Reset Code';
    $subheading = 'Use the verification code below to reset your password.';
@endphp

@component('emails.layouts.online-order', compact('siteName', 'logoUrl', 'supportPhone', 'supportEmail', 'heading', 'subheading'))
    <p style="margin:0 0 16px;font-size:15px;line-height:1.6;color:#334155;">
        Hello <strong>{{ $recipientName }}</strong>,
    </p>
    <p style="margin:0 0 24px;font-size:14px;line-height:1.6;color:#475569;">
        We received a request to reset your password. Enter this one-time verification code on the reset page to continue:
    </p>

    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="margin-bottom:24px;">
        <tr>
            <td align="center">
                <div style="display:inline-block;background:linear-gradient(135deg,#eef2ff 0%,#f8fafc 100%);border:2px dashed #6366f1;border-radius:12px;padding:24px 32px;">
                    <p style="margin:0 0 8px;font-size:11px;font-weight:700;letter-spacing:0.18em;text-transform:uppercase;color:#6366f1;">
                        Verification Code
                    </p>
                    <p style="margin:0;font-size:36px;font-weight:800;letter-spacing:0.35em;color:#0f172a;font-family:'Courier New',Courier,monospace;">
                        {{ $otp }}
                    </p>
                </div>
            </td>
        </tr>
    </table>

    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#fff7ed;border:1px solid #fed7aa;border-radius:8px;margin-bottom:20px;">
        <tr>
            <td style="padding:14px 18px;">
                <p style="margin:0;font-size:13px;line-height:1.6;color:#9a3412;">
                    <strong>Expires in {{ $expiryMinutes }} minutes.</strong>
                    For your security, do not share this code with anyone.
                </p>
            </td>
        </tr>
    </table>

    <p style="margin:0;font-size:13px;line-height:1.6;color:#64748b;">
        If you did not request a password reset, you can safely ignore this email. Your password will remain unchanged.
    </p>
@endcomponent
