<?php

namespace App\Mail;

use App\Support\WebsiteSettings;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PasswordResetOtpMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $recipientName,
        public string $otp,
        public int $expiryMinutes,
    ) {}

    public function envelope(): Envelope
    {
        $siteName = (string) WebsiteSettings::get('website_name', config('app.name'));

        return new Envelope(
            subject: "Your password reset code — {$siteName}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.password-reset-otp',
            with: [
                'recipientName' => $this->recipientName,
                'otp' => $this->otp,
                'expiryMinutes' => $this->expiryMinutes,
                'siteName' => WebsiteSettings::get('website_name', config('app.name')),
                'logoUrl' => WebsiteSettings::logoUrl(),
                'supportPhone' => WebsiteSettings::get('phone'),
                'supportEmail' => WebsiteSettings::get('email'),
            ],
        );
    }
}
