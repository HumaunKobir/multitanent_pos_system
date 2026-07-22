<?php

use App\Enums\VoucherType;
use App\Models\Branch;
use App\Models\Party;
use App\Models\User;
use App\Models\Voucher;
use Inertia\Testing\AssertableInertia as Assert;

test('guests are redirected from parties index', function () {
    $this->get(route('party.parties.index'))->assertRedirect(route('login'));
});

test('parties index loads for authorized users', function () {
    $this->artisan('permissions:sync');

    $branch = Branch::factory()->create();
    $user = User::factory()->create(['branch_id' => $branch->id]);
    $user->givePermissionTo('party.parties.view');

    Party::factory()->create(['branch_id' => $branch->id, 'name' => 'Walk-in Client']);

    $this->actingAs($user)
        ->get(route('party.parties.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/party/parties/index')
            ->has('parties.data', 1)
            ->where('parties.data.0.name', 'Walk-in Client'));
});

test('party can be created updated and deleted', function () {
    $this->artisan('permissions:sync');

    $branch = Branch::factory()->create();
    $user = User::factory()->create(['branch_id' => $branch->id]);
    $user->givePermissionTo([
        'party.parties.create',
        'party.parties.update',
        'party.parties.delete',
    ]);

    $this->actingAs($user)
        ->post(route('party.parties.store'), [
            'name' => 'Misc Party',
            'phone' => '01700000001',
            'email' => 'misc@example.com',
            'address' => 'Dhaka',
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    $party = Party::query()->where('name', 'Misc Party')->first();
    expect($party)->not->toBeNull()
        ->and($party->branch_id)->toBe($branch->id)
        ->and($party->phone)->toBe('01700000001');

    $this->actingAs($user)
        ->put(route('party.parties.update', $party), [
            'name' => 'Updated Party',
            'phone' => '01700000002',
            'email' => 'updated@example.com',
            'address' => 'Chittagong',
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    expect($party->fresh()->name)->toBe('Updated Party');

    $this->actingAs($user)
        ->delete(route('party.parties.destroy', $party))
        ->assertRedirect()
        ->assertSessionHas('success');

    expect(Party::query()->find($party->id))->toBeNull();
});

test('party used on voucher cannot be deleted', function () {
    $this->artisan('permissions:sync');

    $branch = Branch::factory()->create();
    $user = User::factory()->create(['branch_id' => $branch->id]);
    $user->givePermissionTo('party.parties.delete');

    $party = Party::factory()->create(['branch_id' => $branch->id]);

    Voucher::query()->create([
        'type' => VoucherType::Income,
        'voucher_no' => 'INC-DEL-'.fake()->unique()->numerify('####'),
        'date' => now()->toDateString(),
        'party_type' => Party::class,
        'party_id' => $party->id,
        'total_amount' => 100,
        'branch_id' => $branch->id,
        'created_by' => $user->id,
    ]);

    $this->actingAs($user)
        ->from(route('party.parties.index'))
        ->delete(route('party.parties.destroy', $party))
        ->assertRedirect(route('party.parties.index'))
        ->assertSessionHas('error');

    expect(Party::query()->find($party->id))->not->toBeNull();
});
