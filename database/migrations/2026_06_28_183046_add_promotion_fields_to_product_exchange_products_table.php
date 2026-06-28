<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_exchange_products', function (Blueprint $table) {
            $table->foreignId('new_promotion_id')->nullable()->after('new_unit_price')->constrained('promotions')->nullOnDelete();
            $table->decimal('new_original_unit_price', 12, 2)->nullable()->after('new_promotion_id');
            $table->decimal('new_free_quantity', 12, 2)->default(0)->after('new_original_unit_price');
            $table->decimal('new_promotion_discount', 12, 2)->default(0)->after('new_free_quantity');
            $table->json('new_promotion_meta')->nullable()->after('new_promotion_discount');
        });
    }

    public function down(): void
    {
        Schema::table('product_exchange_products', function (Blueprint $table) {
            $table->dropConstrainedForeignId('new_promotion_id');
            $table->dropColumn([
                'new_original_unit_price',
                'new_free_quantity',
                'new_promotion_discount',
                'new_promotion_meta',
            ]);
        });
    }
};
