<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('sale_return_products', function (Blueprint $table) {
            $table->foreignId('product_exchange_product_id')
                ->nullable()
                ->after('sell_product_id')
                ->constrained('product_exchange_products')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sale_return_products', function (Blueprint $table) {
            $table->dropConstrainedForeignId('product_exchange_product_id');
        });
    }
};
