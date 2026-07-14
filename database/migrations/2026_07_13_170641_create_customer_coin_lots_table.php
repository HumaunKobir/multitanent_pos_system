<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A prior failed attempt can leave the table without foreign keys.
        if (
            Schema::hasTable('customer_coin_lots')
            && Schema::getConnection()->getDriverName() === 'mysql'
        ) {
            $hasForeignKeys = collect(DB::select(
                'SELECT CONSTRAINT_NAME
                 FROM information_schema.TABLE_CONSTRAINTS
                 WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME = ?
                   AND CONSTRAINT_TYPE = ?',
                ['customer_coin_lots', 'FOREIGN KEY'],
            ))->isNotEmpty();

            if (! $hasForeignKeys) {
                Schema::drop('customer_coin_lots');
            }
        }

        if (Schema::hasTable('customer_coin_lots')) {
            return;
        }

        Schema::create('customer_coin_lots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('earn_transaction_id')->constrained('customer_coin_transactions')->cascadeOnDelete();
            $table->foreignId('sell_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('product_exchange_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('original_coins', 15, 2);
            $table->decimal('remaining_coins', 15, 2);
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index(['expires_at', 'remaining_coins']);
            $table->index(['customer_id', 'expires_at']);
            $table->index(['sell_id']);
            $table->index(['product_exchange_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_coin_lots');
    }
};
