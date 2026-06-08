<?php

use App\Actions\Steadfast\SendOrderToSteadfast;
use App\Actions\Steadfast\SyncSteadfastOrderStatus;
use App\Enums\OrderStatus;
use App\Exceptions\SteadfastCourierException;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\OnlineOrder;
use App\Models\OnlineOrderProduct;
use App\Models\Product;
use App\Models\User;
use App\Services\EcommerceBranchService;
use App\Support\SteadfastPhone;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Permission;

function steadfastTestConfig(): void
{
    config([
        'steadfast.api_key' => 'test-api-key',
        'steadfast.secret_key' => 'test-secret-key',
        'steadfast.base_url' => 'https://portal.packzy.com/api/v1',
        'steadfast.invoice_prefix' => 'ORD',
    ]);
}

function steadfastEcommerceBranch(): Branch
{
    EcommerceBranchService::resetResolvedId();

    return Branch::query()->firstOrCreate(
        ['name' => EcommerceBranchService::BRANCH_NAME],
        Branch::factory()->make(['name' => EcommerceBranchService::BRANCH_NAME])->toArray(),
    );
}

function onlineOrderViewer(): User
{
    test()->artisan('permissions:sync');

    $user = User::factory()->create(['branch_id' => steadfastEcommerceBranch()->id]);
    Permission::findOrCreate('online-order.view', 'web');
    Permission::findOrCreate('online-order.update', 'web');
    $user->givePermissionTo(['online-order.view', 'online-order.update']);

    return $user;
}

function createSteadfastReadyOrder(array $overrides = []): OnlineOrder
{
    $product = Product::factory()->create();

    $order = OnlineOrder::create(array_merge([
        'name' => 'John Smith',
        'email' => 'john@example.com',
        'phone' => '01712345678',
        'address' => 'House 44, Road 2/A, Dhanmondi, Dhaka 1209',
        'payment_method' => 'cod',
        'delivery_charge' => 60,
        'subtotal' => 1000,
        'total' => 1060,
        'payment_status' => 'Pending',
        'status' => OrderStatus::Processing,
    ], $overrides));

    OnlineOrderProduct::create([
        'online_order_id' => $order->id,
        'product_id' => $product->id,
        'name' => $product->name,
        'price' => 1000,
        'quantity' => 1,
        'total_price' => 1000,
    ]);

    return $order->fresh(['products']);
}

function steadfastCreateOrderFake(int $orderId, array $overrides = []): void
{
    Http::fake([
        'https://portal.packzy.com/api/v1/create_order' => Http::response(array_merge([
            'status' => 200,
            'message' => 'Consignment has been created successfully.',
            'consignment' => [
                'consignment_id' => 1424107,
                'invoice' => 'ORD-'.$orderId,
                'tracking_code' => '15BAEB8A',
                'recipient_name' => 'John Smith',
                'recipient_phone' => '01712345678',
                'recipient_address' => 'House 44, Road 2/A, Dhanmondi, Dhaka 1209',
                'cod_amount' => 1060,
                'status' => 'in_review',
            ],
        ], $overrides), 200),
    ]);
}

test('steadfast phone normalizes valid bangladeshi numbers', function () {
    expect(SteadfastPhone::normalize('01712345678'))->toBe('01712345678');
    expect(SteadfastPhone::normalize('+8801712345678'))->toBe('01712345678');
    expect(SteadfastPhone::normalize('8801712345678'))->toBe('01712345678');
    expect(SteadfastPhone::normalize('1712345678'))->toBe('01712345678');
});

test('steadfast phone rejects invalid numbers', function () {
    SteadfastPhone::normalize('12345');
})->throws(SteadfastCourierException::class);

test('send order to steadfast creates consignment and marks order shipping', function () {
    steadfastTestConfig();

    $order = createSteadfastReadyOrder();
    steadfastCreateOrderFake($order->id);

    $updated = app(SendOrderToSteadfast::class)->execute($order);

    expect($updated->courier)->toBe('steadfast')
        ->and($updated->courier_invoice)->toBe('ORD-'.$order->id)
        ->and($updated->courier_consignment_id)->toBe(1424107)
        ->and($updated->courier_tracking_code)->toBe('15BAEB8A')
        ->and($updated->courier_status)->toBe('in_review')
        ->and($updated->status)->toBe(OrderStatus::Shipping)
        ->and($updated->courier_sent_at)->not->toBeNull();

    Http::assertSent(function ($request) use ($order) {
        return $request->url() === 'https://portal.packzy.com/api/v1/create_order'
            && $request['invoice'] === 'ORD-'.$order->id
            && $request['recipient_phone'] === '01712345678'
            && $request['cod_amount'] == 1060
            && $request['delivery_type'] === 0;
    });
});

test('send order to steadfast uses zero cod for prepaid sslcommerz orders', function () {
    steadfastTestConfig();

    $order = createSteadfastReadyOrder([
        'payment_method' => 'sslcommerz',
        'payment_status' => 'Paid',
    ]);

    steadfastCreateOrderFake($order->id);

    app(SendOrderToSteadfast::class)->execute($order);

    Http::assertSent(fn ($request) => $request['cod_amount'] == 0);
});

test('send order to steadfast blocks unpaid sslcommerz orders', function () {
    steadfastTestConfig();

    $order = createSteadfastReadyOrder([
        'payment_method' => 'sslcommerz',
        'payment_status' => 'Pending',
    ]);

    app(SendOrderToSteadfast::class)->execute($order);
})->throws(SteadfastCourierException::class, 'Prepaid orders must be paid before sending to Steadfast.');

test('send order to steadfast blocks duplicate submissions', function () {
    steadfastTestConfig();

    $order = createSteadfastReadyOrder([
        'courier' => 'steadfast',
        'courier_consignment_id' => 999,
        'courier_tracking_code' => 'ABC12345',
    ]);

    app(SendOrderToSteadfast::class)->execute($order);
})->throws(SteadfastCourierException::class, 'This order has already been sent to Steadfast.');

test('sync steadfast order status updates courier status from api', function () {
    steadfastTestConfig();

    $order = createSteadfastReadyOrder([
        'courier' => 'steadfast',
        'courier_consignment_id' => 1424107,
        'courier_tracking_code' => '15BAEB8A',
        'courier_status' => 'in_review',
        'status' => OrderStatus::Shipping,
    ]);

    $order->update(['courier_invoice' => $order->courierInvoice()]);

    Http::fake([
        'https://portal.packzy.com/api/v1/status_by_invoice/*' => Http::response([
            'status' => 200,
            'delivery_status' => 'pending',
        ], 200),
    ]);

    $updated = app(SyncSteadfastOrderStatus::class)->execute($order);

    expect($updated->courier_status)->toBe('pending');
});

test('superadmin is redirected from online orders', function () {
    $this->actingAs(User::factory()->create(['branch_id' => null]))
        ->get(route('online-order.index'))
        ->assertRedirect(route('dashboard'));
});

test('admin can view online orders index', function () {
    $user = onlineOrderViewer();
    $order = createSteadfastReadyOrder([
        'phone' => fake()->unique()->numerify('017########'),
    ]);

    $this->actingAs($user)
        ->get(route('online-order.index', ['search' => $order->phone]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/online-order/index')
            ->has('orders.data', 1)
            ->where('orders.data.0.id', $order->id)
        );
});

test('admin can view online order details', function () {
    $user = onlineOrderViewer();
    $order = createSteadfastReadyOrder();

    $this->actingAs($user)
        ->get(route('online-order.show', $order))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/online-order/show')
            ->where('order.id', $order->id)
            ->where('canSendToSteadfast', true)
        );
});

test('admin can send order to steadfast from controller', function () {
    steadfastTestConfig();

    $user = onlineOrderViewer();
    $order = createSteadfastReadyOrder();
    steadfastCreateOrderFake($order->id);

    $this->actingAs($user)
        ->post(route('online-order.send-steadfast', $order))
        ->assertRedirect()
        ->assertSessionHas('success');

    expect($order->fresh()->courier_tracking_code)->toBe('15BAEB8A');
});

test('admin can sync steadfast status from controller', function () {
    steadfastTestConfig();

    $user = onlineOrderViewer();
    $order = createSteadfastReadyOrder([
        'courier' => 'steadfast',
        'courier_consignment_id' => 1424107,
        'courier_tracking_code' => '15BAEB8A',
        'courier_status' => 'in_review',
        'status' => OrderStatus::Shipping,
    ]);

    $order->update(['courier_invoice' => $order->courierInvoice()]);

    Http::fake([
        'https://portal.packzy.com/api/v1/status_by_invoice/*' => Http::response([
            'status' => 200,
            'delivery_status' => 'delivered',
        ], 200),
    ]);

    $this->actingAs($user)
        ->patch(route('online-order.sync-steadfast', $order))
        ->assertRedirect()
        ->assertSessionHas('success');

    expect($order->fresh()->courier_status)->toBe('delivered');
});

test('steadfast sync command updates awaiting orders', function () {
    steadfastTestConfig();

    $order = createSteadfastReadyOrder([
        'courier' => 'steadfast',
        'courier_consignment_id' => 1424108,
        'courier_tracking_code' => '15BAEB8B',
        'courier_status' => 'pending',
        'status' => OrderStatus::Shipping,
    ]);

    $order->update(['courier_invoice' => $order->courierInvoice()]);

    Http::fake([
        'https://portal.packzy.com/api/v1/status_by_invoice/*' => Http::response([
            'status' => 200,
            'delivery_status' => 'delivered',
        ], 200),
    ]);

    Artisan::call('steadfast:sync-statuses');

    expect($order->fresh()->courier_status)->toBe('delivered');
});

test('customer order details include courier tracking when available', function () {
    $customer = Customer::factory()->create();

    $order = OnlineOrder::create([
        'customer_id' => $customer->id,
        'name' => $customer->name,
        'phone' => $customer->phone,
        'address' => 'Dhaka',
        'delivery_charge' => 60,
        'subtotal' => 1000,
        'total' => 1060,
        'status' => OrderStatus::Shipping,
        'payment_status' => 'Pending',
        'courier' => 'steadfast',
        'courier_tracking_code' => '15BAEB8A',
        'courier_status' => 'pending',
    ]);

    $this->actingAs($customer, 'customer')
        ->get(route('customer.order.details', $order->id))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('frontend/customer/order-details')
            ->where('order.courier_tracking_code', '15BAEB8A')
            ->where('order.courier_status', 'pending')
        );
});
