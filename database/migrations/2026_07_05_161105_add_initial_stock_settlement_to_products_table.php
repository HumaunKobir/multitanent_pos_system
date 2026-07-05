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
        Schema::table('products', function (Blueprint $table) {
            $table->foreignId('initial_stock_supplier_id')
                ->nullable()
                ->after('discount_price')
                ->constrained('suppliers')
                ->nullOnDelete();
            $table->decimal('initial_stock_paid_amount', 12, 2)->default(0)->after('initial_stock_supplier_id');
            $table->foreignId('initial_stock_payment_account_id')
                ->nullable()
                ->after('initial_stock_paid_amount')
                ->constrained('chart_of_accounts')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropConstrainedForeignId('initial_stock_payment_account_id');
            $table->dropColumn('initial_stock_paid_amount');
            $table->dropConstrainedForeignId('initial_stock_supplier_id');
        });
    }
};
