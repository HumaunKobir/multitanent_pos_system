<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_exchanges', function (Blueprint $table) {
            // How much of overpaid_amount the customer has already paid back.
            $table->decimal('overpaid_collected_amount', 12, 2)->default(0)->after('overpaid_amount');
        });

        // A collection line now settles either a due sale OR an exchange overpayment,
        // so sell_id becomes optional and product_exchange_id joins it.
        Schema::table('customer_payment_allocations', function (Blueprint $table) {
            $table->dropForeign(['sell_id']);
        });

        Schema::table('customer_payment_allocations', function (Blueprint $table) {
            $table->foreignId('sell_id')->nullable()->change();
        });

        Schema::table('customer_payment_allocations', function (Blueprint $table) {
            $table->foreign('sell_id')->references('id')->on('sells')->restrictOnDelete();

            $table->foreignId('product_exchange_id')
                ->nullable()
                ->after('sell_id')
                ->constrained('product_exchanges')
                ->restrictOnDelete();

            $table->unique(
                ['customer_payment_id', 'product_exchange_id'],
                'customer_payment_alloc_exchange_unique',
            );
        });
    }

    public function down(): void
    {
        Schema::table('customer_payment_allocations', function (Blueprint $table) {
            $table->dropUnique('customer_payment_alloc_exchange_unique');
            $table->dropConstrainedForeignId('product_exchange_id');
        });

        Schema::table('customer_payment_allocations', function (Blueprint $table) {
            $table->dropForeign(['sell_id']);
        });

        Schema::table('customer_payment_allocations', function (Blueprint $table) {
            $table->foreignId('sell_id')->nullable(false)->change();
        });

        Schema::table('customer_payment_allocations', function (Blueprint $table) {
            $table->foreign('sell_id')->references('id')->on('sells')->restrictOnDelete();
        });

        Schema::table('product_exchanges', function (Blueprint $table) {
            $table->dropColumn('overpaid_collected_amount');
        });
    }
};
