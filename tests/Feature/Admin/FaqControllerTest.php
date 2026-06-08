<?php

use App\Models\Branch;
use App\Models\Faq;
use App\Models\User;
use App\Services\EcommerceBranchService;

function faqEcommerceBranch(): Branch
{
    EcommerceBranchService::resetResolvedId();

    return Branch::query()->firstOrCreate(
        ['name' => EcommerceBranchService::BRANCH_NAME],
        Branch::factory()->make(['name' => EcommerceBranchService::BRANCH_NAME])->toArray(),
    );
}

function faqEcommerceUser(): User
{
    return User::factory()->create(['branch_id' => faqEcommerceBranch()->id]);
}

test('guests are redirected from faq settings', function () {
    $this->get(route('setting.faq.index'))->assertRedirect(route('login'));
});

test('ecommerce branch user can manage faq with permission', function () {
    test()->artisan('permissions:sync');

    $user = faqEcommerceUser();
    $user->givePermissionTo(
        'setting.faq.view',
        'setting.faq.create',
        'setting.faq.update',
        'setting.faq.delete',
    );

    $suffix = fake()->unique()->uuid();

    $faq = Faq::factory()->create([
        'question' => "How do I track my order {$suffix}?",
        'answer' => '<p>Visit My Orders in your account.</p>',
        'sort_order' => 1,
        'status' => 1,
    ]);

    $this->actingAs($user)
        ->get(route('setting.faq.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/setting/faq/index')
            ->where('faqs.data', fn ($items) => collect($items)->contains('question', $faq->question))
        );

    $this->actingAs($user)
        ->post(route('setting.faq.store'), [
            'question' => "What payment methods do you accept {$suffix}?",
            'answer' => '<p>We accept SSLCommerz and COD.</p>',
            'sort_order' => 2,
            'status' => '1',
        ])
        ->assertRedirect(route('setting.faq.index'))
        ->assertSessionHas('success');

    expect(Faq::query()->where('question', "What payment methods do you accept {$suffix}?")->exists())->toBeTrue();

    $this->actingAs($user)
        ->patch(route('setting.faq.update', $faq), [
            'question' => "How can I track my order {$suffix}?",
            'answer' => '<p>Check My Orders in your account dashboard.</p>',
            'sort_order' => 3,
            'status' => '0',
        ])
        ->assertRedirect(route('setting.faq.index'))
        ->assertSessionHas('success');

    expect($faq->fresh())
        ->question->toBe("How can I track my order {$suffix}?")
        ->status->toBe(0);

    $this->actingAs($user)
        ->delete(route('setting.faq.destroy', $faq))
        ->assertRedirect(route('setting.faq.index'))
        ->assertSessionHas('success');

    expect(Faq::query()->whereKey($faq->id)->exists())->toBeFalse();
});

test('faq page shows active database items', function () {
    $suffix = fake()->unique()->uuid();

    $faq = Faq::factory()->create([
        'question' => "Dynamic FAQ question {$suffix}?",
        'answer' => '<p>Dynamic FAQ answer.</p>',
        'sort_order' => 1,
        'status' => 1,
    ]);

    Faq::factory()->create([
        'question' => "Inactive FAQ question {$suffix}?",
        'answer' => '<p>Should not appear.</p>',
        'sort_order' => 2,
        'status' => 0,
    ]);

    $this->get(route('faq'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('frontend/faq')
            ->where('items', fn ($items) => collect($items)
                ->contains(fn ($item) => $item['question'] === $faq->question && $item['answer'] === $faq->answer)
                && ! collect($items)->contains(fn ($item) => str_contains($item['question'], "Inactive FAQ question {$suffix}"))
            )
        );
});
