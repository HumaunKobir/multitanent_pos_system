<?php

use App\Mail\SubscriberNewsletterMail;
use App\Models\Branch;
use App\Models\ConfigDictionary;
use App\Models\Subscriber;
use App\Models\User;
use App\Services\EcommerceBranchService;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;

function subscriberListSuperAdmin(): User
{
    return User::factory()->create(['branch_id' => null]);
}

function subscriberListBranchUser(): User
{
    $branch = Branch::factory()->create();

    return User::factory()->create(['branch_id' => $branch->id]);
}

function subscriberListEcommerceBranch(): Branch
{
    EcommerceBranchService::resetResolvedId();

    return Branch::query()->firstOrCreate(
        ['name' => EcommerceBranchService::BRANCH_NAME],
        Branch::factory()->make(['name' => EcommerceBranchService::BRANCH_NAME])->toArray(),
    );
}

function subscriberListEcommerceUser(array $permissions = ['subscriber-list.view', 'subscriber-list.delete', 'subscriber-list.send-mail']): User
{
    $branch = subscriberListEcommerceBranch();
    $user = User::factory()->create(['branch_id' => $branch->id]);

    foreach ($permissions as $permission) {
        Permission::findOrCreate($permission, 'web');
        $user->givePermissionTo($permission);
    }

    return $user;
}

test('guests are redirected from subscriber list', function () {
    $this->get('/subscriber-list')->assertRedirect(route('login'));
});

test('non ecommerce branch users are redirected from subscriber list', function () {
    $this->actingAs(subscriberListBranchUser())
        ->get('/subscriber-list')
        ->assertRedirect(route('branch-panel.dashboard'));
});

test('ecommerce branch user can view subscribers', function () {
    $this->artisan('permissions:sync');

    $prefix = 'sub-ec-'.uniqid();

    $visible = Subscriber::factory()->create([
        'email' => "{$prefix}@example.com",
    ]);
    Subscriber::factory()->create([
        'email' => 'other-'.uniqid().'@example.com',
    ]);

    $this->actingAs(subscriberListEcommerceUser())
        ->get('/subscriber-list?search='.$prefix)
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/subscriber-list/index')
            ->has('subscribers.data', 1)
            ->where('subscribers.data.0.id', $visible->id)
        );
});

test('superadmin can view subscriber list', function () {
    $this->actingAs(subscriberListSuperAdmin())
        ->get('/subscriber-list')
        ->assertOk();
});

test('ecommerce branch user can delete a subscriber', function () {
    $subscriber = Subscriber::factory()->create();

    $this->actingAs(subscriberListEcommerceUser())
        ->delete("/subscriber-list/{$subscriber->id}")
        ->assertRedirect(route('subscriber-list.index'))
        ->assertSessionHas('success');

    expect(Subscriber::query()->find($subscriber->id))->toBeNull();
});

function subscriberListMailSettings(): void
{
    ConfigDictionary::setMany([
        'smtp_enabled' => '1',
        'smtp_host' => 'smtp.example.com',
        'smtp_port' => '587',
        'smtp_username' => 'mailer',
        'smtp_password' => 'secret',
        'smtp_encryption' => 'tls',
        'mail_from_address' => 'newsletter@coolness.test',
        'mail_from_name' => 'Coolness Point',
    ]);
}

test('ecommerce branch user can send mail to a single active subscriber', function () {
    Mail::fake();
    subscriberListMailSettings();

    $subscriber = Subscriber::factory()->create([
        'email' => 'subscriber-'.uniqid().'@example.com',
        'status' => true,
    ]);

    $this->actingAs(subscriberListEcommerceUser())
        ->post("/subscriber-list/{$subscriber->id}/mail", [
            'subject' => 'Summer Sale',
            'body' => "Hello,\n\nCheck out our latest offers.",
        ])
        ->assertRedirect(route('subscriber-list.index'))
        ->assertSessionHas('success');

    Mail::assertSent(SubscriberNewsletterMail::class, function (SubscriberNewsletterMail $mail) use ($subscriber) {
        return $mail->hasTo($subscriber->email)
            && $mail->mailSubject === 'Summer Sale'
            && str_contains($mail->mailBody, 'latest offers');
    });
});

test('single subscriber mail is rejected when smtp is not configured', function () {
    Mail::fake();

    ConfigDictionary::set('smtp_enabled', '0');

    $subscriber = Subscriber::factory()->create(['status' => true]);

    $this->actingAs(subscriberListEcommerceUser())
        ->post("/subscriber-list/{$subscriber->id}/mail", [
            'subject' => 'Hello',
            'body' => 'Test message',
        ])
        ->assertRedirect(route('subscriber-list.index'))
        ->assertSessionHas('error');

    Mail::assertNothingSent();
});

test('ecommerce branch user can send bulk mail to selected subscribers', function () {
    Mail::fake();
    subscriberListMailSettings();

    $prefix = 'bulk-'.uniqid();
    $first = Subscriber::factory()->create(['email' => "{$prefix}-1@example.com", 'status' => true]);
    $second = Subscriber::factory()->create(['email' => "{$prefix}-2@example.com", 'status' => true]);
    Subscriber::factory()->inactive()->create(['email' => "{$prefix}-inactive@example.com"]);

    $this->actingAs(subscriberListEcommerceUser())
        ->post('/subscriber-list/mail/bulk', [
            'subject' => 'Weekly Update',
            'body' => 'Here is your weekly update.',
            'subscriber_ids' => [$first->id, $second->id],
        ])
        ->assertRedirect(route('subscriber-list.index'))
        ->assertSessionHas('success');

    Mail::assertSent(SubscriberNewsletterMail::class, 2);
});

test('ecommerce branch user can send bulk mail to all active subscribers', function () {
    Mail::fake();
    subscriberListMailSettings();

    $prefix = 'all-active-'.uniqid();
    Subscriber::factory()->create(['email' => "{$prefix}-1@example.com", 'status' => true]);
    Subscriber::factory()->create(['email' => "{$prefix}-2@example.com", 'status' => true]);
    Subscriber::factory()->inactive()->create(['email' => "{$prefix}-inactive@example.com"]);

    $activeCount = Subscriber::query()->active()->count();

    $this->actingAs(subscriberListEcommerceUser())
        ->post('/subscriber-list/mail/bulk', [
            'subject' => 'Store News',
            'body' => 'Important announcement.',
            'all_active' => true,
        ])
        ->assertRedirect(route('subscriber-list.index'))
        ->assertSessionHas('success');

    Mail::assertSent(SubscriberNewsletterMail::class, $activeCount);
});

test('bulk mail requires recipients when all active is not selected', function () {
    subscriberListMailSettings();

    $this->actingAs(subscriberListEcommerceUser())
        ->post('/subscriber-list/mail/bulk', [
            'subject' => 'Hello',
            'body' => 'Test message',
        ])
        ->assertSessionHasErrors('subscriber_ids');
});

test('subscriber list page exposes mail configuration state', function () {
    subscriberListMailSettings();

    $this->actingAs(subscriberListEcommerceUser())
        ->get('/subscriber-list')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/subscriber-list/index')
            ->where('mailConfigured', true)
            ->has('activeSubscriberCount')
        );
});
