<?php

namespace App\Mail;

use App\Models\OnlineOrder;
use App\Services\OnlineOrderInvoicePdfService;
use App\Support\WebsiteSettings;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OnlineOrderPlacedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public OnlineOrder $order) {}

    public function envelope(): Envelope
    {
        $siteName = (string) WebsiteSettings::get('website_name');

        return new Envelope(
            subject: "Order Confirmation #{$this->order->id} — {$siteName}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.online-order-placed',
            with: $this->sharedViewData(),
        );
    }

    /**
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        $pdf = app(OnlineOrderInvoicePdfService::class)->output($this->order);

        return [
            Attachment::fromData(fn (): string => $pdf, $this->order->invoiceNumber().'.pdf')
                ->withMime('application/pdf'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function sharedViewData(): array
    {
        return [
            'order' => $this->order,
            'invoiceNumber' => $this->order->invoiceNumber(),
            'siteName' => WebsiteSettings::get('website_name'),
            'logoUrl' => WebsiteSettings::logoUrl(),
            'supportPhone' => WebsiteSettings::get('phone'),
            'supportEmail' => WebsiteSettings::get('email'),
            'supportTime' => WebsiteSettings::get('support_time'),
        ];
    }
}
