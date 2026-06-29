<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_exchanges', function (Blueprint $table) {
            $table->decimal('due_amount', 12, 2)->default(0)->after('paid_amount');
        });

        Schema::table('product_exchange_products', function (Blueprint $table) {
            $table->decimal('new_line_discount', 12, 2)->default(0)->after('new_unit_price');
        });
    }

    public function down(): void
    {
        Schema::table('product_exchanges', function (Blueprint $table) {
            $table->dropColumn('due_amount');
        });

        Schema::table('product_exchange_products', function (Blueprint $table) {
            $table->dropColumn('new_line_discount');
        });
    }
};
