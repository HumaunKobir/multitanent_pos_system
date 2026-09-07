<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchases', function (Blueprint $table) {
            $table->foreignId('product_id')
                ->nullable()
                ->after('supplier_id')
                ->constrained('products')
                ->nullOnDelete();

            $table->unique(['product_id', 'purchase_type'], 'purchases_product_type_unique');
        });
    }

    public function down(): void
    {
        Schema::table('purchases', function (Blueprint $table) {
            $table->dropUnique('purchases_product_type_unique');
            $table->dropConstrainedForeignId('product_id');
        });
    }
};
