<?php

use App\Enums\OrderStatus;
use App\Models\ConfigDictionary;
use App\Models\Customer;
use App\Models\OnlineOrder;
use App\Models\Product;
use App\Services\SslCommerzGateway;
use Illuminate\Support\Str;
use Mockery\MockInterface;

function sslCommerzCartWithProduct(): array
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

function createPendingSslCommerzOrder(?string $transactionId = null): OnlineOrder
{
    Product::factory()->create(['sale_price' => 1200, 'discount_price' => 0]);

    return OnlineOrder::create([
        'name' => 'Test Customer',
        'phone' => fake()->unique()->numerify('017########'),
        'address' => 'Dhaka',
        'payment_method' => 'sslcommerz',
        'transaction_id' => $transactionId ?? ('CP-'.strtoupper(Str::ulid()->toString())),
        'delivery_charge' => 60,
        'subtotal' => 1200,
        'total' => 1260,
        'payment_status' => 'Pending',
        'status' => OrderStatus::Pending,
    ]);
}

test('sslcommerz checkout creates pending order and clears cart when gateway accepts', function () {
    $customer = Customer::factory()->create();
    $phone = fake()->unique()->numerify('017########');
    session(['cart' => sslCommerzCartWithProduct()]);

    $this->mock(SslCommerzGateway::class, function (MockInterface $mock): void {
        $mock->shouldReceive('initiatePayment')
            ->once()
            ->andReturn(['url' => 'https://sandbox.sslcommerz.com/EasyCheckOut/test', 'error' => null]);
    });

    $this->withInertiaHeaders()
        ->actingAs($customer, 'customer')
        ->post(route('checkout.store'), [
            'name' => 'Karim Ahmed',
            'phone' => $phone,
            'address' => 'Dhaka',
            'payment_method' => 'sslcommerz',
            'delivery_zone' => 1,
        ])
        ->assertStatus(409)
        ->assertHeader('X-Inertia-Location', 'https://sandbox.sslcommerz.com/EasyCheckOut/test');

    $order = OnlineOrder::query()->where('phone', $phone)->latest('id')->first();

    expect($order)->not->toBeNull()
        ->and($order->payment_method)->toBe('sslcommerz')
        ->and($order->payment_status)->toBe('Pending')
        ->and($order->transaction_id)->not->toBeNull()
        ->and($order->products)->toHaveCount(1);

    expect(session('cart'))->toBeNull();
});

test('sslcommerz checkout marks order failed when gateway returns error', function () {
    $customer = Customer::factory()->create();
    $phone = fake()->unique()->numerify('017########');
    session(['cart' => sslCommerzCartWithProduct()]);

    $this->mock(SslCommerzGateway::class, function (MockInterface $mock): void {
        $mock->shouldReceive('initiatePayment')
            ->once()
            ->andReturn(['url' => null, 'error' => 'Store credential error']);
    });

    $this->actingAs($customer, 'customer')
        ->post(route('checkout.store'), [
            'name' => 'Karim Ahmed',
            'phone' => $phone,
            'address' => 'Dhaka',
            'payment_method' => 'sslcommerz',
            'delivery_zone' => 1,
        ])
        ->assertRedirect(route('checkout'))
        ->assertSessionHas('error', 'Store credential error');

    $order = OnlineOrder::query()->where('phone', $phone)->latest('id')->first();

    expect($order->payment_status)->toBe('Failed');
});

test('sslcommerz success callback marks order paid after validation', function () {
    $cash = seedAccountingAccounts();
    ConfigDictionary::setMany([
        'online_sslcommerz_payment_account_id' => (string) $cash->id,
        'online_cod_payment_account_id' => (string) $cash->id,
    ]);

    $order = createPendingSslCommerzOrder();

    $this->mock(SslCommerzGateway::class, function (MockInterface $mock): void {
        $mock->shouldReceive('validateOrder')
            ->once()
            ->andReturn(true);
    });

    $this->post(route('payment.success'), [
        'tran_id' => $order->transaction_id,
        'amount' => '1260.00',
        'currency' => 'BDT',
        'val_id' => 'test-val-id',
    ])
        ->assertRedirect(route('checkout.success', ['order' => $order->id]))
        ->assertSessionHas('success', 'Payment completed successfully!');

    expect($order->fresh()->payment_status)->toBe('Paid');
});

test('sslcommerz failure callback marks pending order as failed', function () {
    $transactionId = 'CP-101-FAILED01-'.fake()->unique()->numerify('####');
    $order = createPendingSslCommerzOrder($transactionId);

    $this->post(route('payment.failure'), [
        'tran_id' => $transactionId,
    ])
        ->assertRedirect(route('checkout'))
        ->assertSessionHas('error');

    expect($order->fresh()->payment_status)->toBe('Failed');
});

test('sslcommerz cancel callback marks pending order as cancelled', function () {
    $transactionId = 'CP-102-CANCEL01-'.fake()->unique()->numerify('####');
    $order = createPendingSslCommerzOrder($transactionId);

    $this->post(route('payment.cancel'), [
        'tran_id' => $transactionId,
    ])
        ->assertRedirect(route('cart'))
        ->assertSessionHas('error');

    expect($order->fresh()->payment_status)->toBe('Cancelled');
});

test('sslcommerz ipn marks order paid and is idempotent', function () {
    $cash = seedAccountingAccounts();
    ConfigDictionary::setMany([
        'online_sslcommerz_payment_account_id' => (string) $cash->id,
        'online_cod_payment_account_id' => (string) $cash->id,
    ]);

    $transactionId = 'CP-103-IPN0001-'.fake()->unique()->numerify('####');
    $order = createPendingSslCommerzOrder($transactionId);

    $this->mock(SslCommerzGateway::class, function (MockInterface $mock): void {
        $mock->shouldReceive('validateOrder')
            ->once()
            ->andReturn(true);
    });

    $this->post(route('payment.ipn'), [
        'tran_id' => $transactionId,
        'val_id' => 'test-val-id',
    ])
        ->assertOk()
        ->assertSee('Transaction is successfully Completed');

    expect($order->fresh()->payment_status)->toBe('Paid');

    $this->post(route('payment.ipn'), [
        'tran_id' => $transactionId,
        'val_id' => 'test-val-id',
    ])
        ->assertOk()
        ->assertSee('Transaction is already successfully Completed');
});

test('sslcommerz success redirects to order success when already paid', function () {
    $transactionId = 'CP-104-ALREADY1-'.fake()->unique()->numerify('####');
    $order = createPendingSslCommerzOrder($transactionId);
    $order->update(['payment_status' => 'Paid']);

    $this->post(route('payment.success'), [
        'tran_id' => $transactionId,
    ])
        ->assertRedirect(route('checkout.success', ['order' => $order->id]));
});
