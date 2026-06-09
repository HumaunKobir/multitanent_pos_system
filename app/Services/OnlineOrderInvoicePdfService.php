<?php

namespace App\Services;

use App\Models\OnlineOrder;
use App\Support\WebsiteSettings;
use Barryvdh\DomPDF\Facade\Pdf;
use Symfony\Component\HttpFoundation\Response;

class OnlineOrderInvoicePdfService
{
    /**
     * @return array<string, mixed>
     */
    public function buildViewData(OnlineOrder $order): array
    {
        $order->loadMissing('products');

        return [
            'order' => $order,
            'invoiceNumber' => $order->invoiceNumber(),
            'company' => [
                'name' => WebsiteSettings::get('website_name'),
                'phone' => WebsiteSettings::get('phone'),
                'email' => WebsiteSettings::get('email'),
                'address' => WebsiteSettings::get('address'),
                'logoDataUri' => WebsiteSettings::logoDataUri(),
            ],
        ];
    }

    public function generate(OnlineOrder $order): \Barryvdh\DomPDF\PDF
    {
        $fontCache = storage_path('fonts');

        if (! is_dir($fontCache)) {
            mkdir($fontCache, 0775, true);
        }

        return Pdf::loadView('pdf.online-order-invoice', $this->buildViewData($order))
            ->setPaper('a4')
            ->setOptions([
                'isHtml5ParserEnabled' => true,
                'isRemoteEnabled' => true,
                'fontDir' => $fontCache,
                'fontCache' => $fontCache,
            ]);
    }

    public function download(OnlineOrder $order, ?string $filename = null): Response
    {
        $filename ??= $order->invoiceNumber().'.pdf';

        return $this->generate($order)->download($filename);
    }

    public function output(OnlineOrder $order): string
    {
        return $this->generate($order)->output();
    }
}
