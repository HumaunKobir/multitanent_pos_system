<?php

namespace App\Http\Controllers\Setting;

use App\Http\Controllers\Controller;
use App\Http\Requests\Setting\UpdateWebsiteSettingRequest;
use App\Mail\OnlineOrderPaymentConfirmedMail;
use App\Mail\OnlineOrderPlacedMail;
use App\Models\ConfigDictionary;
use App\Models\OnlineOrder;
use App\Models\OnlineOrderProduct;
use App\Services\OnlineOrderInvoicePdfService;
use App\Support\MailSettings;
use App\Support\WebsiteSettings;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class WebsiteSettingController extends Controller
{
    public function edit(): Response
    {
        $this->authorize('setting.website.view');

        return Inertia::render('admin/setting/website/index', [
            'settings' => WebsiteSettings::forAdmin(),
            'mailSettings' => MailSettings::forAdmin(),
        ]);
    }

    public function update(UpdateWebsiteSettingRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $mailKeys = MailSettings::keys();
        $mailValues = [];

        foreach ($mailKeys as $key) {
            if ($key === 'smtp_password') {
                if (! empty($validated['smtp_password'])) {
                    $mailValues[$key] = (string) $validated['smtp_password'];
                }

                continue;
            }

            if (array_key_exists($key, $validated)) {
                $mailValues[$key] = is_null($validated[$key]) ? '' : (string) $validated[$key];
            }
        }

        $textValues = collect($validated)
            ->except(array_merge(['logo', 'fav_icon'], $mailKeys))
            ->map(fn ($value) => is_null($value) ? '' : (string) $value)
            ->all();

        ConfigDictionary::setMany($textValues);

        if ($mailValues !== []) {
            ConfigDictionary::setMany($mailValues);
        }

        if ($request->hasFile('logo')) {
            $this->replaceUploadedFile('logo', $request->file('logo')->store('website', 'public'));
        }

        if ($request->hasFile('fav_icon')) {
            $this->replaceUploadedFile('fav_icon', $request->file('fav_icon')->store('website', 'public'));
        }

        return redirect()
            ->route('setting.website.edit')
            ->with('success', 'Website settings updated successfully.');
    }

    public function previewOrderEmail(): View
    {
        $this->authorize('setting.website.view');

        $order = $this->previewOrder();

        return view('emails.online-order-placed', (new OnlineOrderPlacedMail($order))->sharedViewData());
    }

    public function previewPaymentEmail(): View
    {
        $this->authorize('setting.website.view');

        $order = $this->previewOrder();
        $order->payment_status = 'Paid';

        return view('emails.online-order-payment-confirmed', (new OnlineOrderPaymentConfirmedMail($order))->sharedViewData());
    }

    public function previewInvoice(OnlineOrderInvoicePdfService $invoicePdfService): HttpResponse
    {
        $this->authorize('setting.website.view');

        return $invoicePdfService->download($this->previewOrder(), 'invoice-preview.pdf');
    }

    protected function previewOrder(): OnlineOrder
    {
        $order = OnlineOrder::query()->with('products')->latest('id')->first();

        if ($order !== null) {
            return $order;
        }

        $order = new OnlineOrder([
            'id' => 1001,
            'name' => 'Sample Customer',
            'email' => 'customer@example.com',
            'phone' => '01700000000',
            'address' => 'Dhaka, Bangladesh',
            'payment_method' => 'sslcommerz',
            'transaction_id' => 'CP-1001-SAMPLE01',
            'delivery_charge' => 60,
            'subtotal' => 1200,
            'total' => 1260,
            'payment_status' => 'Pending',
            'created_at' => now(),
        ]);

        $order->setRelation('products', collect([
            new OnlineOrderProduct([
                'name' => 'Sample Product',
                'sku' => 'SKU-001',
                'price' => 1200,
                'quantity' => 1,
                'total_price' => 1200,
            ]),
        ]));

        return $order;
    }

    protected function replaceUploadedFile(string $key, string $path): void
    {
        $previous = ConfigDictionary::get($key);

        if ($previous && ! str_starts_with($previous, 'http')) {
            Storage::disk('public')->delete($previous);
        }

        ConfigDictionary::set($key, $path);
    }
}
