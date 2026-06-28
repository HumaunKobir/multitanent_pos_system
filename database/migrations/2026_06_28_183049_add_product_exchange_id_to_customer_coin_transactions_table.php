<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customer_coin_transactions', function (Blueprint $table) {
            $table->foreignId('product_exchange_id')->nullable()->after('sell_id')->constrained('product_exchanges')->nullOnDelete();
            $table->index(['product_exchange_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::table('customer_coin_transactions', function (Blueprint $table) {
            $table->dropIndex(['product_exchange_id', 'type']);
            $table->dropConstrainedForeignId('product_exchange_id');
        });
    }
};
