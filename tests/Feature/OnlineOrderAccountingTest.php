<?php

use App\Enums\OrderStatus;
use App\Enums\SystemAccountKey;
use App\Models\Batch;
use App\Models\Branch;
use App\Models\ConfigDictionary;
use App\Models\Ledger;
use App\Models\OnlineOrder;
use App\Models\OnlineOrderProduct;
use App\Models\Product;
use App\Models\Transaction;
use App\Models\User;
use App\Services\EcommerceBranchService;
use App\Services\InventoryAccountingService;
use App\Services\OnlineOrderAccountingService;
use App\Services\SslCommerzGateway;
use App\Services\SystemAccountService;
use Mockery\MockInterface;
use Spatie\Permission\Models\Permission;

function onlineOrderAdmin(): User
{
    test()->artisan('permissions:sync');

    EcommerceBranchService::resetResolvedId();

    $branch = Branch::query()->firstOrCreate(
        ['name' => EcommerceBranchService::BRANCH_NAME],
        Branch::factory()->make(['name' => EcommerceBranchService::BRANCH_NAME])->toArray(),
    );

    $user = User::factory()->create(['branch_id' => $branch->id]);
    Permission::findOrCreate('online-order.update', 'web');
    $user->givePermissionTo('online-order.update');

    return $user;
}

/**
 * @return array{order: OnlineOrder, product: Product, batch: Batch}
 */
function onlineOrderWithStock(array $orderOverrides = []): array
{
    $product = Product::factory()->create(['sale_price' => 1200, 'discount_price' => 0]);
    $batch = Batch::factory()->for($product)->withStock(10)->create([
        'purchase_price' => 800,
        'branch_id' => EcommerceBranchService::resolveIdStatic(),
    ]);

    $order = OnlineOrder::create(array_merge([
        'name' => 'Online Buyer',
        'phone' => fake()->unique()->numerify('017########'),
        'address' => 'Dhaka',
        'payment_method' => 'sslcommerz',
        'transaction_id' => 'CP-ACCT-'.fake()->unique()->numerify('####'),
        'delivery_charge' => 60,
        'subtotal' => 1200,
        'total' => 1260,
        'payment_status' => 'Paid',
        'status' => OrderStatus::Pending,
    ], $orderOverrides));

    OnlineOrderProduct::create([
        'online_order_id' => $order->id,
        'product_id' => $product->id,
        'name' => $product->name,
        'price' => 1200,
        'quantity' => 1,
        'total_price' => 1200,
    ]);

    return compact('order', 'product', 'batch');
}

function configureOnlinePaymentAccounts(int $sslAccountId, int $codAccountId): void
{
    ConfigDictionary::setMany([
        'online_sslcommerz_payment_account_id' => (string) $sslAccountId,
        'online_cod_payment_account_id' => (string) $codAccountId,
    ]);
}

function assertBalancedTransaction(?Transaction $transaction): void
{
    expect($transaction)->not->toBeNull();

    $ledgers = Ledger::query()->where('transaction_id', $transaction->id)->get();
    $debit = round($ledgers->sum(fn (Ledger $line) => (float) $line->debit), 2);
    $credit = round($ledgers->sum(fn (Ledger $line) => (float) $line->credit), 2);

    expect($debit)->toBe($credit)
        ->and($debit)->toBeGreaterThan(0);
}

test('sslcommerz prepayment posts cash debit and customer deposits credit', function () {
    $cash = seedAccountingAccounts();
    configureOnlinePaymentAccounts($cash->id, $cash->id);
    SystemAccountService::seed();

    ['order' => $order] = onlineOrderWithStock();

    app(OnlineOrderAccountingService::class)->recordPrepaymentIfNeeded($order);

    $transaction = app(InventoryAccountingService::class)->findOnlineOrderTransaction($order, 'prepayment');
    assertBalancedTransaction($transaction);

    $ledgers = Ledger::query()->where('transaction_id', $transaction->id)->get();
    $cashAccountId = $cash->id;
    $depositsAccountId = SystemAccountService::resolve(SystemAccountKey::AdvanceFromCustomer)->id;

    expect($ledgers->firstWhere('account_id', $cashAccountId)?->debit)->toBe('1260.00')
        ->and($ledgers->firstWhere('account_id', $depositsAccountId)?->credit)->toBe('1260.00');
});

test('sslcommerz fulfillment recognizes revenue and cogs from customer deposits', function () {
    $cash = seedAccountingAccounts();
    configureOnlinePaymentAccounts($cash->id, $cash->id);
    SystemAccountService::seed();

    ['order' => $order, 'batch' => $batch] = onlineOrderWithStock();

    $accounting = app(OnlineOrderAccountingService::class);
    $accounting->recordPrepaymentIfNeeded($order);
    $accounting->fulfill($order->fresh());

    $transaction = app(InventoryAccountingService::class)->findOnlineOrderTransaction($order, 'fulfillment');
    assertBalancedTransaction($transaction);

    $order->refresh();
    expect($order->status)->toBe(OrderStatus::Delivered);

    $batch->refresh();
    expect((float) $batch->available)->toBe(9.0);

    $revenueAccountId = SystemAccountService::resolve(SystemAccountKey::ProductSales)->id;
    $otherIncomeAccountId = SystemAccountService::resolve(SystemAccountKey::OtherIncome)->id;
    $cogsAccountId = SystemAccountService::resolve(SystemAccountKey::CostOfGoodsSold)->id;
    $ledgers = Ledger::query()->where('transaction_id', $transaction->id)->get();

    expect(round($ledgers->where('account_id', $revenueAccountId)->sum('credit'), 2))->toBe(1200.0)
        ->and(round($ledgers->where('account_id', $otherIncomeAccountId)->sum('credit'), 2))->toBe(60.0)
        ->and(round($ledgers->where('account_id', $cogsAccountId)->sum('debit'), 2))->toBe(800.0);
});

test('cod fulfillment posts cash receipt and revenue at delivery', function () {
    $cash = seedAccountingAccounts();
    configureOnlinePaymentAccounts($cash->id, $cash->id);
    SystemAccountService::seed();

    ['order' => $order] = onlineOrderWithStock([
        'payment_method' => 'cod',
        'payment_status' => 'Pending',
        'transaction_id' => null,
    ]);

    app(OnlineOrderAccountingService::class)->fulfill($order->fresh());

    $transaction = app(InventoryAccountingService::class)->findOnlineOrderTransaction($order, 'fulfillment');
    assertBalancedTransaction($transaction);

    $order->refresh();
    expect($order->status)->toBe(OrderStatus::Delivered)
        ->and($order->payment_status)->toBe('Paid');

    $ledgers = Ledger::query()->where('transaction_id', $transaction->id)->get();
    expect(round($ledgers->where('account_id', $cash->id)->sum('debit'), 2))->toBe(1260.0);
});

test('admin can fulfill online order through route', function () {
    $user = onlineOrderAdmin();
    $cash = seedAccountingAccounts(branchId: $user->branch_id);
    configureOnlinePaymentAccounts($cash->id, $cash->id);
    SystemAccountService::seed($user->branch_id);

    ['order' => $order] = onlineOrderWithStock(['payment_method' => 'cod', 'payment_status' => 'Pending', 'transaction_id' => null]);

    $this->actingAs($user)
        ->patch(route('online-order.fulfill', $order))
        ->assertRedirect()
        ->assertSessionHas('success');

    expect($order->fresh()->status)->toBe(OrderStatus::Delivered);
});

test('sslcommerz success callback records prepayment journal', function () {
    $cash = seedAccountingAccounts();
    configureOnlinePaymentAccounts($cash->id, $cash->id);
    SystemAccountService::seed();

    ['order' => $order] = onlineOrderWithStock(['payment_status' => 'Pending']);

    $this->mock(SslCommerzGateway::class, function (MockInterface $mock): void {
        $mock->shouldReceive('validateOrder')->once()->andReturn(true);
    });

    $this->post(route('payment.success'), [
        'tran_id' => $order->transaction_id,
        'amount' => '1260.00',
        'currency' => 'BDT',
        'val_id' => 'test-val-id',
    ])->assertRedirect(route('checkout.success', ['order' => $order->id]));

    expect($order->fresh()->payment_status)->toBe('Paid');

    $transaction = app(InventoryAccountingService::class)->findOnlineOrderTransaction($order, 'prepayment');
    assertBalancedTransaction($transaction);
});

test('prepayment recording is idempotent', function () {
    $cash = seedAccountingAccounts();
    configureOnlinePaymentAccounts($cash->id, $cash->id);

    ['order' => $order] = onlineOrderWithStock();
    $service = app(OnlineOrderAccountingService::class);

    $service->recordPrepaymentIfNeeded($order);
    $service->recordPrepaymentIfNeeded($order->fresh());

    $count = Transaction::query()
        ->where('source_type', OnlineOrder::class)
        ->where('source_id', $order->id)
        ->where('description', 'like', 'Online order prepayment —%')
        ->count();

    expect($count)->toBe(1);
});
