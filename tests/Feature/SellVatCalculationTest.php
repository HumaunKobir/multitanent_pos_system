<?php

use App\Enums\DiscountType;
use App\Models\Batch;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Sell;
use App\Models\User;
use Spatie\Permission\Models\Permission;

function sellVatUser(array $permissions = []): User
{
    $branch = Branch::factory()->create();
    $user = User::factory()->create(['branch_id' => $branch->id]);

    foreach ($permissions as $permission) {
        Permission::findOrCreate($permission, 'web');
        $user->givePermissionTo($permission);
    }

    return $user;
}

test('sale vat is calculated after discounts then round off is applied', function () {
    $this->artisan('permissions:sync');

    $user = sellVatUser(['inventory.sell.create']);
    $cash = seedAccountingAccounts(branchId: $user->branch_id);

    $customer = Customer::factory()->create(['branch_id' => $user->branch_id]);
    $product = Product::factory()->create(['branch_id' => $user->branch_id, 'sale_price' => 1000]);
    Batch::factory()->for($product)->withStock(10)->create([
        'branch_id' => $user->branch_id,
        'purchase_price' => 100,
    ]);

    // Gross 1000, invoice 50 → VAT base 950; 10% → 95; then round off 10 → net 1035
    $this->actingAs($user)
        ->post('/inventory/sell', [
            'customer_id' => $customer->id,
            'date' => now()->format('Y-m-d'),
            'discount_type' => DiscountType::Flat->value,
            'discount_value' => '50',
            'round_off_amount' => '10',
            'special_discount_id' => null,
            'vat' => '10',
            'paid_amount' => '1035',
            'payment_account_id' => $cash->id,
            'items' => [[
                'product_id' => $product->id,
                'variation_id' => null,
                'unit_price' => '1000',
                'quantity' => '1',
                'discount' => '0',
            ]],
        ])
        ->assertRedirect();

    $sell = Sell::query()->latest('id')->first();

    expect($sell)->not->toBeNull()
        ->and((float) $sell->gross_amount)->toBe(1000.0)
        ->and((float) $sell->discount)->toBe(50.0)
        ->and((float) $sell->round_off_amount)->toBe(10.0)
        ->and((float) $sell->vat)->toBe(95.0)
        ->and((float) $sell->net_amount)->toBe(1035.0);
});

test('sale vat is calculated after line invoice and special discounts', function () {
    $this->artisan('permissions:sync');

    $user = sellVatUser(['inventory.sell.create']);
    $cash = seedAccountingAccounts(branchId: $user->branch_id);

    $customer = Customer::factory()->create(['branch_id' => $user->branch_id]);
    $product = Product::factory()->create(['branch_id' => $user->branch_id, 'sale_price' => 1000]);
    Batch::factory()->for($product)->withStock(10)->create([
        'branch_id' => $user->branch_id,
        'purchase_price' => 100,
    ]);

    // Gross 1000, line discount 50, invoice 50 → VAT base 900; 10% → 90; net 990
    $this->actingAs($user)
        ->post('/inventory/sell', [
            'customer_id' => $customer->id,
            'date' => now()->format('Y-m-d'),
            'discount_type' => DiscountType::Flat->value,
            'discount_value' => '50',
            'special_discount_id' => null,
            'vat' => '10',
            'paid_amount' => '990',
            'payment_account_id' => $cash->id,
            'items' => [[
                'product_id' => $product->id,
                'variation_id' => null,
                'unit_price' => '1000',
                'quantity' => '1',
                'discount' => '50',
            ]],
        ])
        ->assertRedirect();

    $sell = Sell::query()->latest('id')->first();

    expect($sell)->not->toBeNull()
        ->and((float) $sell->gross_amount)->toBe(1000.0)
        ->and((float) $sell->discount)->toBe(50.0)
        ->and((float) $sell->vat)->toBe(90.0)
        ->and((float) $sell->net_amount)->toBe(990.0);
});
