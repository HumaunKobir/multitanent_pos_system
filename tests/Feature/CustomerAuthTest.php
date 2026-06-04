<?php

use App\Enums\CustomerRegistrationType;
use App\Models\Customer;
use App\Models\OnlineOrder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

function uniqueCustomerPhone(): string
{
    return fake()->unique()->numerify('017########');
}

test('customer login page loads', function () {
    $this->get(route('customer.login'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('frontend/customer/login'));
});

test('customer can login with email', function () {
    $customer = Customer::factory()->create([
        'email' => fake()->unique()->safeEmail(),
        'password' => bcrypt('password123'),
    ]);

    $this->post(route('customer.login'), [
        'email' => $customer->email,
        'password' => 'password123',
    ])->assertRedirect(route('customer.dashboard'));

    $this->assertAuthenticatedAs($customer, 'customer');
});

test('customer can login with phone number', function () {
    $customer = Customer::factory()->create([
        'phone' => uniqueCustomerPhone(),
        'password' => bcrypt('password123'),
    ]);

    $this->post(route('customer.login'), [
        'email' => $customer->phone,
        'password' => 'password123',
    ])->assertRedirect(route('customer.dashboard'));

    $this->assertAuthenticatedAs($customer, 'customer');
});

test('customer login fails with wrong password', function () {
    $customer = Customer::factory()->create([
        'email' => fake()->unique()->safeEmail(),
        'password' => bcrypt('correctpassword'),
    ]);

    $this->post(route('customer.login'), [
        'email' => $customer->email,
        'password' => 'wrongpassword',
    ])->assertSessionHasErrors(['email']);
});

test('customer register page loads', function () {
    $this->get(route('customer.register'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('frontend/customer/register'));
});

test('customer can register', function () {
    $phone = uniqueCustomerPhone();

    $this->post('/customer/register', [
        'name' => 'নতুন গ্রাহক',
        'email' => fake()->unique()->safeEmail(),
        'phone' => $phone,
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ])->assertRedirect(route('customer.dashboard'));

    $this->assertDatabaseHas('customers', [
        'phone' => $phone,
        'registration_type' => CustomerRegistrationType::Online->value,
    ]);
});

test('offline customer can register online with same phone', function () {
    $phone = uniqueCustomerPhone();

    Customer::factory()->offline()->create([
        'phone' => $phone,
        'name' => 'Store Customer',
    ]);

    $this->post('/customer/register', [
        'name' => 'Store Customer Online',
        'phone' => $phone,
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ])->assertRedirect(route('customer.dashboard'));

    expect(Customer::where('phone', $phone)->first())
        ->name->toBe('Store Customer Online')
        ->registration_type->toBe(CustomerRegistrationType::Online);
});

test('customer register requires name, phone, and password', function () {
    $this->post('/customer/register', [])
        ->assertSessionHasErrors(['name', 'phone', 'password']);
});

test('customer register prevents duplicate phone for online customers', function () {
    $phone = uniqueCustomerPhone();

    Customer::factory()->create([
        'phone' => $phone,
        'registration_type' => CustomerRegistrationType::Online,
    ]);

    $this->post('/customer/register', [
        'name' => 'Another',
        'phone' => $phone,
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ])->assertSessionHasErrors(['phone']);
});

test('customer dashboard requires authentication', function () {
    $this->get(route('customer.dashboard'))
        ->assertRedirect(route('customer.login'));
});

test('authenticated customer can visit dashboard', function () {
    $customer = Customer::factory()->create();

    $this->actingAs($customer, 'customer')
        ->get(route('customer.dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('frontend/customer/dashboard'));
});

test('authenticated customer can view orders', function () {
    $customer = Customer::factory()->create();

    $this->actingAs($customer, 'customer')
        ->get(route('customer.orders'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('frontend/customer/orders'));
});

test('authenticated customer can view own order details', function () {
    $customer = Customer::factory()->create();

    $order = OnlineOrder::create([
        'customer_id' => $customer->id,
        'name' => $customer->name,
        'phone' => $customer->phone,
        'address' => 'Dhaka',
        'delivery_charge' => 60,
        'subtotal' => 1000,
        'total' => 1060,
        'status' => 2,
        'payment_status' => 'pending',
    ]);

    $this->actingAs($customer, 'customer')
        ->get(route('customer.order.details', $order->id))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('frontend/customer/order-details')
            ->has('order', fn ($orderProp) => $orderProp
                ->where('id', $order->id)
                ->where('status', 2)
                ->etc()
            ));
});

test('customer cannot view another customers order details', function () {
    $owner = Customer::factory()->create();
    $other = Customer::factory()->create();

    $order = OnlineOrder::create([
        'customer_id' => $owner->id,
        'name' => $owner->name,
        'phone' => $owner->phone,
        'address' => 'Dhaka',
        'delivery_charge' => 60,
        'subtotal' => 500,
        'total' => 560,
        'status' => 1,
        'payment_status' => 'pending',
    ]);

    $this->actingAs($other, 'customer')
        ->get(route('customer.order.details', $order->id))
        ->assertNotFound();
});

test('customer can logout', function () {
    $customer = Customer::factory()->create();

    $this->actingAs($customer, 'customer')
        ->post(route('customer.logout'))
        ->assertRedirect(route('home'));

    $this->assertGuest('customer');
});

test('guest customer is redirected from dashboard to login', function () {
    $this->get(route('customer.dashboard'))
        ->assertRedirect(route('customer.login'));
});

test('authenticated customer cannot visit login page', function () {
    $customer = Customer::factory()->create();

    $this->actingAs($customer, 'customer')
        ->get(route('customer.login'))
        ->assertRedirect();
});

test('authenticated customer can visit profile settings', function () {
    $customer = Customer::factory()->create();

    $this->actingAs($customer, 'customer')
        ->get(route('customer.settings'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('frontend/customer/profile'));
});

test('authenticated customer can update profile', function () {
    $customer = Customer::factory()->create(['name' => 'Old Name']);

    $this->actingAs($customer, 'customer')
        ->patch(route('customer.settings.update'), [
            'name' => 'New Name',
            'phone' => $customer->phone,
            'email' => $customer->email,
        ])
        ->assertRedirect();

    expect($customer->fresh()->name)->toBe('New Name');
});

test('authenticated customer can upload profile image', function () {
    Storage::fake('public');

    $customer = Customer::factory()->create();

    $this->actingAs($customer, 'customer')
        ->patch(route('customer.settings.update'), [
            'name' => $customer->name,
            'phone' => $customer->phone,
            'email' => $customer->email,
            'image' => UploadedFile::fake()->image('avatar.jpg'),
        ])
        ->assertRedirect();

    $customer->refresh();

    expect($customer->image)->not->toBeNull();
    Storage::disk('public')->assertExists($customer->image);
});

test('authenticated customer can remove profile image', function () {
    Storage::fake('public');

    $path = UploadedFile::fake()->image('avatar.jpg')->store('customers', 'public');
    $customer = Customer::factory()->create(['image' => $path]);

    $this->actingAs($customer, 'customer')
        ->patch(route('customer.settings.update'), [
            'name' => $customer->name,
            'phone' => $customer->phone,
            'email' => $customer->email,
            'remove_image' => true,
        ])
        ->assertRedirect();

    $customer->refresh();

    expect($customer->image)->toBeNull();
    Storage::disk('public')->assertMissing($path);
});
