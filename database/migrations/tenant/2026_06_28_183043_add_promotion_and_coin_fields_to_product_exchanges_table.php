<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_exchanges', function (Blueprint $table) {
            $table->decimal('promotion_discount_total', 12, 2)->default(0)->after('special_discount_amount');
            $table->decimal('coins_redeemed', 15, 2)->default(0)->after('round_off_amount');
            $table->decimal('coin_discount_amount', 15, 2)->default(0)->after('coins_redeemed');
            $table->decimal('coins_earned', 15, 2)->default(0)->after('coin_discount_amount');
        });
    }

    public function down(): void
    {
        Schema::table('product_exchanges', function (Blueprint $table) {
            $table->dropColumn([
                'promotion_discount_total',
                'coins_redeemed',
                'coin_discount_amount',
                'coins_earned',
            ]);
        });
    }
};
