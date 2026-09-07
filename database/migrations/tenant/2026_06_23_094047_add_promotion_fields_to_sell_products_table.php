<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sell_products', function (Blueprint $table) {
            $table->decimal('original_unit_price', 10, 2)->nullable()->after('unit_price');
            $table->foreignId('promotion_id')->nullable()->after('discount')->constrained('promotions')->nullOnDelete();
            $table->decimal('promotion_discount', 10, 2)->default(0)->after('promotion_id');
            $table->json('promotion_meta')->nullable()->after('promotion_discount');
        });
    }

    public function down(): void
    {
        Schema::table('sell_products', function (Blueprint $table) {
            $table->dropForeign(['promotion_id']);
            $table->dropColumn(['original_unit_price', 'promotion_id', 'promotion_discount', 'promotion_meta']);
        });
    }
};
