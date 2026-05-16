<?php

use App\Models\Customer;

test('customer login page loads', function () {
    $this->get(route('customer.login'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('frontend/customer/login'));
});

test('customer can login with email', function () {
    $customer = Customer::factory()->create([
        'email' => 'customer@example.com',
        'password' => bcrypt('password123'),
    ]);

    $this->post(route('customer.login'), [
        'email' => 'customer@example.com',
        'password' => 'password123',
    ])->assertRedirect(route('customer.dashboard'));

    $this->assertAuthenticatedAs($customer, 'customer');
});

test('customer can login with phone number', function () {
    $customer = Customer::factory()->create([
        'phone' => '01711111111',
        'password' => bcrypt('password123'),
    ]);

    $this->post(route('customer.login'), [
        'email' => '01711111111',
        'password' => 'password123',
    ])->assertRedirect(route('customer.dashboard'));

    $this->assertAuthenticatedAs($customer, 'customer');
});

test('customer login fails with wrong password', function () {
    Customer::factory()->create([
        'email' => 'wrong@example.com',
        'password' => bcrypt('correctpassword'),
    ]);

    $this->post(route('customer.login'), [
        'email' => 'wrong@example.com',
        'password' => 'wrongpassword',
    ])->assertSessionHasErrors(['email']);
});

test('customer register page loads', function () {
    $this->get(route('customer.register'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('frontend/customer/register'));
});

test('customer can register', function () {
    $this->post(route('customer.register'), [
        'name' => 'নতুন গ্রাহক',
        'email' => 'new@example.com',
        'phone' => '01722222222',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ])->assertRedirect(route('customer.dashboard'));

    $this->assertDatabaseHas('customers', ['phone' => '01722222222']);
});

test('customer register requires name, phone, and password', function () {
    $this->post(route('customer.register'), [])
        ->assertSessionHasErrors(['name', 'phone', 'password']);
});

test('customer register prevents duplicate phone', function () {
    Customer::factory()->create(['phone' => '01733333333']);

    $this->post(route('customer.register'), [
        'name' => 'Another',
        'phone' => '01733333333',
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
