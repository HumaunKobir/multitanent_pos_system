<?php

use Illuminate\Support\Facades\Schema;

test('exchange overpayment migration can run safely when already applied', function () {
    $migration = require database_path(
        'migrations/2026_07_12_195214_allow_exchange_overpayment_in_customer_payment_allocations.php',
    );

    $migration->up();

    expect(Schema::hasColumn('product_exchanges', 'overpaid_collected_amount'))->toBeTrue();
    expect(Schema::hasColumn('customer_payment_allocations', 'product_exchange_id'))->toBeTrue();
});
