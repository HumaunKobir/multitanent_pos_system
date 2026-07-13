<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
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
