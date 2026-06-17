<?php

use App\Enums\OrderStatus;
use App\Mail\OnlineOrderPaymentConfirmedMail;
use App\Mail\OnlineOrderPlacedMail;
use App\Models\Branch;
use App\Models\ConfigDictionary;
use App\Models\Customer;
use App\Models\OnlineOrder;
use App\Models\OnlineOrderProduct;
use App\Models\Product;
use App\Models\User;
use App\Services\EcommerceBranchService;
use App\Services\OnlineOrderInvoicePdfService;
use App\Services\OnlineOrderNotificationService;
use App\Services\SslCommerzGateway;
use App\Support\DynamicMailConfigurator;
use App\Support\MailSettings;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Mockery\MockInterface;

function onlineOrderMailCart(): array
{
    $product = Product::factory()->create(['sale_price' => 1200, 'discount_price' => 0]);
    $cartKey = $product->id.'-0';

    return [
        $cartKey => [
            'product_id' => $product->id,
            'name' => $product->name,
            'image' => null,
            'price' => 1200.0,
            'quantity' => 1,
            'variation_id' => null,
            'sku' => null,
        ],
    ];
}

function onlineOrderMailCustomer(): Customer
{
    return Customer::factory()->create(['email' => fake()->unique()->safeEmail()]);
}

function createOnlineOrderForMail(array $overrides = []): OnlineOrder
{
    $order = OnlineOrder::create(array_merge([
        'name' => 'Test Customer',
        'email' => 'paid@example.com',
        'phone' => fake()->unique()->numerify('017########'),
        'address' => 'Dhaka',
        'payment_method' => 'sslcommerz',
        'transaction_id' => 'CP-'.strtoupper(Str::random(8)),
        'delivery_charge' => 60,
        'subtotal' => 1200,
        'total' => 1260,
        'payment_status' => 'Pending',
        'status' => OrderStatus::Pending,
    ], $overrides));

    OnlineOrderProduct::create([
        'online_order_id' => $order->id,
        'name' => 'Sample Product',
        'price' => 1200,
        'quantity' => 1,
        'total_price' => 1200,
    ]);

    return $order->fresh(['products']);
}

function invoiceMailEcommerceUser(): User
{
    EcommerceBranchService::resetResolvedId();

    $branch = Branch::query()->firstOrCreate(
        ['name' => EcommerceBranchService::BRANCH_NAME],
        Branch::factory()->make(['name' => EcommerceBranchService::BRANCH_NAME])->toArray(),
    );

    return User::factory()->create(['branch_id' => $branch->id]);
}

function invoiceMailWebsitePayload(array $overrides = []): array
{
    return array_merge([
        'website_name' => 'Coolness Point Test',
        'phone' => '01700000000',
        'email' => 'hello@coolness.test',
        'address' => 'Dhaka, Bangladesh',
        'fb_share_for_withdraw' => 'https://facebook.com/coolness',
        'youtube' => 'https://youtube.com/coolness',
        'twit' => 'https://x.com/coolness',
        'linkend' => 'https://linkedin.com/company/coolness',
        'topnotice1' => 'Free shipping this week',
        'footer_description' => 'Premium fashion delivered nationwide.',
        'support_time' => 'Sat–Thu, 9AM–9PM',
        'delivery_charge_inside_dhaka' => 80,
        'delivery_charge_outside_dhaka' => 150,
        'meta_tags' => 'fashion, apparel',
        'meta_description' => 'Shop quality fashion online.',
        'newsletter_enabled' => '1',
        'newsletter_title' => 'Join our list',
        'newsletter_description' => 'Get offers in your inbox.',
        'newsletter_placeholder' => 'Email here',
        'newsletter_button' => 'Join',
        'smtp_enabled' => '0',
        'smtp_host' => '',
        'smtp_port' => 587,
        'smtp_username' => '',
        'smtp_encryption' => 'tls',
        'mail_from_address' => '',
        'mail_from_name' => '',
    ], $overrides);
}

test('online order invoice number is formatted', function () {
    $order = new OnlineOrder;
    $order->forceFill(['id' => 42]);

    expect($order->invoiceNumber())->toBe('INV-000042');
});

test('online order notification email prefers order email then customer email', function () {
    $customer = Customer::factory()->create(['email' => 'customer@example.com']);
    $order = new OnlineOrder([
        'email' => null,
        'customer_id' => $customer->id,
    ]);
    $order->setRelation('customer', $customer);

    expect($order->notificationEmail())->toBe('customer@example.com');

    $order->email = 'order@example.com';

    expect($order->notificationEmail())->toBe('order@example.com');
});

test('dynamic mail configurator applies smtp settings from website settings', function () {
    ConfigDictionary::setMany([
        'smtp_enabled' => '1',
        'smtp_host' => 'smtp.example.com',
        'smtp_port' => '465',
        'smtp_username' => 'mailer',
        'smtp_password' => 'secret',
        'smtp_encryption' => 'ssl',
        'mail_from_address' => 'orders@coolness.test',
        'mail_from_name' => 'Coolness Orders',
    ]);

    DynamicMailConfigurator::apply();

    expect(Config::get('mail.default'))->toBe('smtp')
        ->and(Config::get('mail.mailers.smtp.host'))->toBe('smtp.example.com')
        ->and(Config::get('mail.mailers.smtp.port'))->toBe(465)
        ->and(Config::get('mail.mailers.smtp.username'))->toBe('mailer')
        ->and(Config::get('mail.mailers.smtp.password'))->toBe('secret')
        ->and(Config::get('mail.mailers.smtp.encryption'))->toBe('ssl')
        ->and(Config::get('mail.from.address'))->toBe('orders@coolness.test')
        ->and(Config::get('mail.from.name'))->toBe('Coolness Orders');
});

test('mail settings are not applied when smtp is disabled', function () {
    Config::set('mail.default', 'log');

    ConfigDictionary::set('smtp_enabled', '0');

    DynamicMailConfigurator::apply();

    expect(Config::get('mail.default'))->toBe('log');
});

test('cod order sends order placed email once with invoice attachment', function () {
    Mail::fake();

    $customer = onlineOrderMailCustomer();
    session(['cart' => onlineOrderMailCart()]);

    $this->actingAs($customer, 'customer')
        ->post(route('checkout.store'), [
            'name' => 'Test Customer',
            'email' => 'buyer@example.com',
            'phone' => fake()->unique()->numerify('017########'),
            'address' => 'Dhaka',
            'payment_method' => 'cod',
            'delivery_zone' => 1,
        ])
        ->assertRedirect();

    Mail::assertSent(OnlineOrderPlacedMail::class, 1);
    Mail::assertNotSent(OnlineOrderPaymentConfirmedMail::class);

    $order = OnlineOrder::query()->where('email', 'buyer@example.com')->latest('id')->first();
    expect($order?->order_email_sent_at)->not->toBeNull();
});

test('sslcommerz payment success sends one combined email with order and payment details', function () {
    Mail::fake();

    seedEcommerceBranchAccounts();

    $order = createOnlineOrderForMail(['email' => 'paid@example.com']);

    $this->mock(SslCommerzGateway::class, function (MockInterface $mock): void {
        $mock->shouldReceive('validateOrder')->once()->andReturn(true);
    });

    $this->post(route('payment.success'), [
        'tran_id' => $order->transaction_id,
        'amount' => '1260.00',
        'currency' => 'BDT',
        'status' => 'VALID',
    ])->assertRedirect(route('checkout.success', ['order' => $order->id]));

    $order = $order->fresh();

    expect($order->payment_status)->toBe('Paid')
        ->and($order->payment_email_sent_at)->not->toBeNull()
        ->and($order->order_email_sent_at)->not->toBeNull();

    Mail::assertSent(OnlineOrderPaymentConfirmedMail::class, function (OnlineOrderPaymentConfirmedMail $mail): bool {
        return $mail->hasTo('paid@example.com') && $mail->isCombined === true;
    });

    Mail::assertSent(OnlineOrderPaymentConfirmedMail::class, 1);
    Mail::assertNotSent(OnlineOrderPlacedMail::class);
});

test('sslcommerz checkout does not send email before payment', function () {
    Mail::fake();

    $customer = onlineOrderMailCustomer();
    session(['cart' => onlineOrderMailCart()]);

    $this->mock(SslCommerzGateway::class, function (MockInterface $mock): void {
        $mock->shouldReceive('initiatePayment')
            ->once()
            ->andReturn(['url' => 'https://sandbox.sslcommerz.com/EasyCheckOut/test', 'error' => null]);
    });

    $this->withInertiaHeaders()
        ->actingAs($customer, 'customer')
        ->post(route('checkout.store'), [
            'name' => 'Karim Ahmed',
            'email' => 'ssl@example.com',
            'phone' => fake()->unique()->numerify('017########'),
            'address' => 'Dhaka',
            'payment_method' => 'sslcommerz',
            'delivery_zone' => 1,
        ])
        ->assertStatus(409);

    Mail::assertNothingSent();

    $order = OnlineOrder::query()->where('email', 'ssl@example.com')->latest('id')->first();
    expect($order?->order_email_sent_at)->toBeNull();
});

test('payment email is not sent twice for the same order', function () {
    Mail::fake();

    $order = createOnlineOrderForMail([
        'email' => 'paid@example.com',
        'payment_status' => 'Paid',
        'payment_email_sent_at' => now(),
        'order_email_sent_at' => now(),
    ]);

    app(OnlineOrderNotificationService::class)->sendPaymentConfirmedOnce($order);

    Mail::assertNothingSent();
});

test('cod order sends separate payment email when payment is collected later', function () {
    Mail::fake();

    $order = createOnlineOrderForMail([
        'email' => 'cod@example.com',
        'payment_method' => 'cod',
        'payment_status' => 'Pending',
        'order_email_sent_at' => now(),
    ]);

    $order->update(['payment_status' => 'Paid']);

    app(OnlineOrderNotificationService::class)->sendPaymentConfirmedOnce($order->fresh(['products']));

    Mail::assertSent(OnlineOrderPaymentConfirmedMail::class, function (OnlineOrderPaymentConfirmedMail $mail): bool {
        return $mail->hasTo('cod@example.com') && $mail->isCombined === false;
    });

    Mail::assertSent(OnlineOrderPlacedMail::class, 0);
    Mail::assertSent(OnlineOrderPaymentConfirmedMail::class, 1);
});

test('order email is not sent twice for the same order', function () {
    Mail::fake();

    $order = createOnlineOrderForMail(['email' => 'once@example.com', 'order_email_sent_at' => now()]);
    $service = app(OnlineOrderNotificationService::class);

    $service->sendOrderPlacedOnce($order);

    Mail::assertNothingSent();
});

test('updating order status to confirmed does not send email', function () {
    Mail::fake();
    test()->artisan('permissions:sync');

    $user = invoiceMailEcommerceUser();
    $user->givePermissionTo('online-order.update');

    $order = createOnlineOrderForMail(['email' => 'status@example.com', 'order_email_sent_at' => now()]);

    $this->actingAs($user)
        ->patch(route('online-order.update-status', ['onlineOrder' => $order->id]), [
            'status' => OrderStatus::Confirmed->value,
        ])
        ->assertRedirect();

    expect($order->fresh()->status)->toBe(OrderStatus::Confirmed);
    Mail::assertNothingSent();
});

test('invoice pdf renders bdt currency symbol', function () {
    $order = createOnlineOrderForMail();

    $html = view('pdf.online-order-invoice', app(OnlineOrderInvoicePdfService::class)->buildViewData($order))->render();

    expect($html)->toContain('data:image/png;base64,')
        ->and($html)->toContain('amount-cell money');
});

test('invoice pdf reflects updated order status', function () {
    $order = createOnlineOrderForMail(['status' => OrderStatus::Confirmed]);

    $html = view('pdf.online-order-invoice', app(OnlineOrderInvoicePdfService::class)->buildViewData($order))->render();

    expect($html)->toContain('Order Status: Confirmed');
});

test('online order invoice pdf can be generated', function () {
    $order = createOnlineOrderForMail();

    $pdf = app(OnlineOrderInvoicePdfService::class)->output($order);

    expect($pdf)->toStartWith('%PDF');
});

test('online order invoice pdf includes website logo when configured', function () {
    $logoPath = 'website/test-invoice-logo.png';
    Storage::disk('public')->put($logoPath, base64_decode(
        'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='
    ));

    ConfigDictionary::set('logo', $logoPath);

    $order = createOnlineOrderForMail();
    $html = view('pdf.online-order-invoice', app(OnlineOrderInvoicePdfService::class)->buildViewData($order))->render();

    expect($html)->toContain('data:image/png;base64,');

    Storage::disk('public')->delete($logoPath);
    ConfigDictionary::set('logo', '');
});

test('authorized admin can download online order invoice pdf', function () {
    test()->artisan('permissions:sync');

    $user = invoiceMailEcommerceUser();
    $user->givePermissionTo('online-order.view');

    $order = createOnlineOrderForMail();

    $this->actingAs($user)
        ->get(route('online-order.invoice', ['onlineOrder' => $order->id]))
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf');
});

test('website settings can store smtp credentials', function () {
    test()->artisan('permissions:sync');

    $user = invoiceMailEcommerceUser();
    $user->givePermissionTo('setting.website.update');

    $payload = invoiceMailWebsitePayload([
        'smtp_enabled' => '1',
        'smtp_host' => 'smtp.mailtrap.io',
        'smtp_port' => 587,
        'smtp_username' => 'user',
        'smtp_password' => 'pass123',
        'smtp_encryption' => 'tls',
        'mail_from_address' => 'noreply@coolness.test',
        'mail_from_name' => 'Coolness Point',
    ]);

    $this->actingAs($user)
        ->put('/setting/website', $payload)
        ->assertRedirect(route('setting.website.edit'));

    expect(MailSettings::get('smtp_host'))->toBe('smtp.mailtrap.io')
        ->and(MailSettings::get('smtp_password'))->toBe('pass123')
        ->and(MailSettings::get('mail_from_address'))->toBe('noreply@coolness.test');
});

test('notification service skips sending when order has no email', function () {
    Mail::fake();

    $order = createOnlineOrderForMail([
        'email' => null,
        'customer_id' => null,
        'payment_method' => 'cod',
    ]);

    app(OnlineOrderNotificationService::class)->sendOrderPlacedOnce($order);

    Mail::assertNothingSent();
});

test('ecommerce user can preview order email template', function () {
    test()->artisan('permissions:sync');

    $user = invoiceMailEcommerceUser();
    $user->givePermissionTo('setting.website.view');

    $this->actingAs($user)
        ->get(route('setting.website.preview-email.order'))
        ->assertOk()
        ->assertSee('Order Confirmed!');
});
