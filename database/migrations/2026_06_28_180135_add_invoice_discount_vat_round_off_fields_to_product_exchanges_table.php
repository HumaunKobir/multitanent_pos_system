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
        Schema::table('product_exchanges', function (Blueprint $table) {
            $table->decimal('discount', 12, 2)->default(0)->after('special_discount_amount');
            $table->string('discount_type')->default('flat')->after('discount');
            $table->decimal('discount_value', 12, 2)->default(0)->after('discount_type');
            $table->decimal('vat', 12, 2)->default(0)->after('discount_value');
            $table->decimal('round_off_amount', 12, 2)->default(0)->after('vat');
            $table->decimal('net_amount', 12, 2)->default(0)->after('round_off_amount');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('product_exchanges', function (Blueprint $table) {
            $table->dropColumn([
                'discount',
                'discount_type',
                'discount_value',
                'vat',
                'round_off_amount',
                'net_amount',
            ]);
        });
    }
};
