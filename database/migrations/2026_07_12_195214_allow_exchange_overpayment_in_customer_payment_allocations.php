<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('product_exchanges', 'overpaid_collected_amount')) {
            Schema::table('product_exchanges', function (Blueprint $table) {
                // How much of overpaid_amount the customer has already paid back.
                $table->decimal('overpaid_collected_amount', 12, 2)->default(0)->after('overpaid_amount');
            });
        }

        // A collection line now settles either a due sale OR an exchange overpayment,
        // so sell_id becomes optional and product_exchange_id joins it.
        if (! Schema::hasColumn('customer_payment_allocations', 'product_exchange_id')) {
            $this->dropForeignKeyIfExists('customer_payment_allocations', 'sell_id');

            Schema::table('customer_payment_allocations', function (Blueprint $table) {
                $table->foreignId('sell_id')->nullable()->change();
            });

            Schema::table('customer_payment_allocations', function (Blueprint $table) {
                if (! $this->foreignKeyExists('customer_payment_allocations', 'customer_payment_allocations_sell_id_foreign')) {
                    $table->foreign('sell_id')->references('id')->on('sells')->restrictOnDelete();
                }

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
    }

    public function down(): void
    {
        if (Schema::hasColumn('customer_payment_allocations', 'product_exchange_id')) {
            Schema::table('customer_payment_allocations', function (Blueprint $table) {
                if ($this->indexExists('customer_payment_allocations', 'customer_payment_alloc_exchange_unique')) {
                    $table->dropUnique('customer_payment_alloc_exchange_unique');
                }

                if ($this->foreignKeyExists('customer_payment_allocations', 'customer_payment_allocations_product_exchange_id_foreign')) {
                    $table->dropConstrainedForeignId('product_exchange_id');
                } elseif (Schema::hasColumn('customer_payment_allocations', 'product_exchange_id')) {
                    $table->dropColumn('product_exchange_id');
                }
            });

            $this->dropForeignKeyIfExists('customer_payment_allocations', 'sell_id');

            Schema::table('customer_payment_allocations', function (Blueprint $table) {
                $table->foreignId('sell_id')->nullable(false)->change();
            });

            Schema::table('customer_payment_allocations', function (Blueprint $table) {
                if (! $this->foreignKeyExists('customer_payment_allocations', 'customer_payment_allocations_sell_id_foreign')) {
                    $table->foreign('sell_id')->references('id')->on('sells')->restrictOnDelete();
                }
            });
        }

        if (Schema::hasColumn('product_exchanges', 'overpaid_collected_amount')) {
            Schema::table('product_exchanges', function (Blueprint $table) {
                $table->dropColumn('overpaid_collected_amount');
            });
        }
    }

    private function dropForeignKeyIfExists(string $table, string $column): void
    {
        $constraint = "{$table}_{$column}_foreign";

        if (! $this->foreignKeyExists($table, $constraint)) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($column): void {
            $blueprint->dropForeign([$column]);
        });
    }

    private function foreignKeyExists(string $table, string $constraint): bool
    {
        if (Schema::getConnection()->getDriverName() !== 'mysql') {
            return true;
        }

        return collect(DB::select(
            'SELECT CONSTRAINT_NAME
             FROM information_schema.TABLE_CONSTRAINTS
             WHERE TABLE_SCHEMA = ?
               AND TABLE_NAME = ?
               AND CONSTRAINT_NAME = ?
               AND CONSTRAINT_TYPE = ?',
            [DB::getDatabaseName(), $table, $constraint, 'FOREIGN KEY'],
        ))->isNotEmpty();
    }

    private function indexExists(string $table, string $index): bool
    {
        if (Schema::getConnection()->getDriverName() !== 'mysql') {
            return true;
        }

        return collect(DB::select(
            'SELECT INDEX_NAME
             FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = ?
               AND TABLE_NAME = ?
               AND INDEX_NAME = ?',
            [DB::getDatabaseName(), $table, $index],
        ))->isNotEmpty();
    }
};
