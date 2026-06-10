<?php

use App\Enums\CustomerRegistrationType;
use App\Enums\OrderStatus;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\OnlineOrder;
use App\Models\User;
use App\Services\EcommerceBranchService;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;

function onlineCustomerEcommerceBranch(): Branch
{
    EcommerceBranchService::resetResolvedId();

    return Branch::query()->firstOrCreate(
        ['name' => EcommerceBranchService::BRANCH_NAME],
        Branch::factory()->make(['name' => EcommerceBranchService::BRANCH_NAME])->toArray(),
    );
}

function onlineCustomerViewer(): User
{
    test()->artisan('permissions:sync');

    $user = User::factory()->create(['branch_id' => onlineCustomerEcommerceBranch()->id]);
    Permission::findOrCreate('online-customer.view', 'web');
    $user->givePermissionTo('online-customer.view');

    return $user;
}

function createOnlineCustomer(array $overrides = []): Customer
{
    return Customer::factory()->create(array_merge([
        'registration_type' => CustomerRegistrationType::Online,
    ], $overrides));
}

function createCustomerOrder(Customer $customer, array $overrides = []): OnlineOrder
{
    return OnlineOrder::create(array_merge([
        'customer_id' => $customer->id,
        'name' => $customer->name,
        'email' => $customer->email,
        'phone' => $customer->phone,
        'address' => $customer->address ?? 'Dhaka',
        'payment_method' => 'cod',
        'delivery_charge' => 60,
        'subtotal' => 1000,
        'total' => 1060,
        'payment_status' => 'Pending',
        'status' => OrderStatus::Pending,
    ], $overrides));
}

test('guest cannot access online customer list', function () {
    $this->get(route('online-customer.index'))
        ->assertRedirect(route('login'));
});

test('user without permission cannot access online customer list', function () {
    $user = User::factory()->create(['branch_id' => onlineCustomerEcommerceBranch()->id]);

    $this->actingAs($user)
        ->get(route('online-customer.index'))
        ->assertForbidden();
});

test('online customer index lists registered online customers with order counts', function () {
    $user = onlineCustomerViewer();

    $customerName = 'Rahim Ahmed '.uniqid();
    $onlineCustomer = createOnlineCustomer(['name' => $customerName, 'phone' => fake()->unique()->numerify('017########')]);
    $offlineCustomer = Customer::factory()->offline()->create(['name' => 'Walk-in Customer']);

    createCustomerOrder($onlineCustomer, ['status' => OrderStatus::Pending, 'payment_method' => 'cod']);
    createCustomerOrder($onlineCustomer, ['status' => OrderStatus::Delivered, 'payment_method' => 'sslcommerz', 'payment_status' => 'Paid']);
    createCustomerOrder($onlineCustomer, ['status' => OrderStatus::Canceled, 'payment_method' => 'cod']);

    $this->actingAs($user)
        ->get(route('online-customer.index', ['search' => $customerName]))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/online-customer/index')
            ->has('customers.data', 1)
            ->where('customers.data.0.name', $customerName)
            ->where('customers.data.0.orders_count', 3)
            ->where('customers.data.0.pending_orders_count', 1)
            ->where('customers.data.0.delivered_orders_count', 1)
            ->where('customers.data.0.canceled_orders_count', 1)
            ->where('customers.data.0.cod_orders_count', 2)
            ->where('customers.data.0.online_payment_orders_count', 1)
        );

    expect($offlineCustomer->registration_type)->toBe(CustomerRegistrationType::Offline);
});

test('online customer index can search by name phone or email', function () {
    $user = onlineCustomerViewer();

    $karimPhone = fake()->unique()->numerify('017########');
    $jamalPhone = fake()->unique()->numerify('017########');

    $karimName = 'Karim Ali '.uniqid();
    $jamalName = 'Jamal Hossain '.uniqid();

    createOnlineCustomer(['name' => $karimName, 'phone' => $karimPhone, 'email' => 'karim-'.uniqid().'@example.com']);
    createOnlineCustomer(['name' => $jamalName, 'phone' => $jamalPhone, 'email' => 'jamal-'.uniqid().'@example.com']);

    $this->actingAs($user)
        ->get(route('online-customer.index', ['search' => $karimName]))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->has('customers.data', 1)
            ->where('customers.data.0.name', $karimName)
        );

    $this->actingAs($user)
        ->get(route('online-customer.index', ['search' => $jamalPhone]))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->has('customers.data', 1)
            ->where('customers.data.0.name', $jamalName)
        );
});

test('online customer show displays profile order stats and history', function () {
    $user = onlineCustomerViewer();

    $customer = createOnlineCustomer([
        'name' => 'Sadia Khan',
        'phone' => fake()->unique()->numerify('017########'),
        'email' => 'sadia-'.uniqid().'@example.com',
        'address' => 'Mirpur, Dhaka',
    ]);

    createCustomerOrder($customer, ['status' => OrderStatus::Pending, 'payment_method' => 'cod']);
    createCustomerOrder($customer, ['status' => OrderStatus::Delivered, 'payment_method' => 'sslcommerz', 'payment_status' => 'Paid']);

    $this->actingAs($user)
        ->get(route('online-customer.show', $customer))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/online-customer/show')
            ->where('customer.name', 'Sadia Khan')
            ->where('customer.phone', $customer->phone)
            ->where('customer.orders_count', 2)
            ->where('customer.pending_orders_count', 1)
            ->where('customer.delivered_orders_count', 1)
            ->where('customer.cod_orders_count', 1)
            ->where('customer.online_payment_orders_count', 1)
            ->has('orders.data', 2)
        );
});

test('offline customer profile returns not found on online customer show', function () {
    $user = onlineCustomerViewer();
    $offlineCustomer = Customer::factory()->offline()->create();

    $this->actingAs($user)
        ->get(route('online-customer.show', $offlineCustomer))
        ->assertNotFound();
});
