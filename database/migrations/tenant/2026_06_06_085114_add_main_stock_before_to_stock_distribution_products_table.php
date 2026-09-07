<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_distribution_products', function (Blueprint $table) {
            $table->decimal('main_stock_before', 12, 2)->nullable()->after('quantity');
        });
    }

    public function down(): void
    {
        Schema::table('stock_distribution_products', function (Blueprint $table) {
            $table->dropColumn('main_stock_before');
        });
    }
};
