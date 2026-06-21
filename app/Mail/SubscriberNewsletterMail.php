<?php

namespace App\Mail;

use App\Support\WebsiteSettings;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SubscriberNewsletterMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $mailSubject,
        public string $mailBody,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->mailSubject,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.subscriber-newsletter',
            with: $this->sharedViewData(),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function sharedViewData(): array
    {
        return [
            'mailBody' => $this->mailBody,
            'siteName' => WebsiteSettings::get('website_name', config('app.name')),
            'logoUrl' => WebsiteSettings::logoUrl(),
            'supportPhone' => WebsiteSettings::get('phone'),
            'supportEmail' => WebsiteSettings::get('email'),
            'supportTime' => WebsiteSettings::get('support_time'),
        ];
    }
}
