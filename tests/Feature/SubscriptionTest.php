<?php

use App\Models\ConfigDictionary;
use App\Models\Subscriber;

test('guest can subscribe with a valid email', function () {
    $email = 'newsletter-'.uniqid().'@example.com';

    $this->from('/')
        ->post(route('subscribe.store'), ['email' => $email])
        ->assertRedirect('/')
        ->assertSessionHas('success', 'Thank you for subscribing!');

    expect(Subscriber::query()->where('email', $email)->value('status'))->toBeTrue();
});

test('subscription validates email', function () {
    $this->from('/')
        ->post(route('subscribe.store'), ['email' => 'not-an-email'])
        ->assertSessionHasErrors('email');
});

test('active subscriber receives already subscribed message', function () {
    $subscriber = Subscriber::factory()->create(['status' => true]);

    $this->from('/')
        ->post(route('subscribe.store'), ['email' => $subscriber->email])
        ->assertRedirect('/')
        ->assertSessionHas('success', 'You are already subscribed to our newsletter.');
});

test('inactive subscriber can resubscribe', function () {
    $subscriber = Subscriber::factory()->inactive()->create();

    $this->from('/')
        ->post(route('subscribe.store'), ['email' => $subscriber->email])
        ->assertRedirect('/')
        ->assertSessionHas('success', 'Welcome back! You have been resubscribed.');

    expect($subscriber->fresh()->status)->toBeTrue();
});

test('newsletter shared props can be configured', function () {
    ConfigDictionary::setMany([
        'newsletter_enabled' => '1',
        'newsletter_title' => 'Join Our Mailing List',
        'newsletter_description' => 'Get updates on new arrivals.',
        'newsletter_placeholder' => 'Enter your email',
        'newsletter_button' => 'Join Now',
    ]);

    $this->get('/')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('newsletter.enabled', true)
            ->where('newsletter.title', 'Join Our Mailing List')
            ->where('newsletter.description', 'Get updates on new arrivals.')
            ->where('newsletter.placeholder', 'Enter your email')
            ->where('newsletter.button', 'Join Now')
        );
});

test('newsletter can be disabled via config', function () {
    ConfigDictionary::set('newsletter_enabled', '0');

    $this->get('/')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('newsletter.enabled', false));
});
