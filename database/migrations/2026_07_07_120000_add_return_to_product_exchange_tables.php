<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_exchanges', function (Blueprint $table) {
            $table->decimal('return_refund_amount', 12, 2)->default(0)->after('gross_amount');
        });

        Schema::table('product_exchange_products', function (Blueprint $table) {
            $table->decimal('return_quantity', 12, 2)->default(0)->after('old_batches');
            $table->decimal('return_unit_price', 12, 2)->default(0)->after('return_quantity');
            $table->decimal('return_refund_amount', 12, 2)->default(0)->after('return_unit_price');
            $table->json('return_batches')->nullable()->after('return_refund_amount');
        });
    }

    public function down(): void
    {
        Schema::table('product_exchanges', function (Blueprint $table) {
            $table->dropColumn('return_refund_amount');
        });

        Schema::table('product_exchange_products', function (Blueprint $table) {
            $table->dropColumn([
                'return_quantity',
                'return_unit_price',
                'return_refund_amount',
                'return_batches',
            ]);
        });
    }
};
