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
            // Set when an edit shrinks the settlement below what was already paid
            // out to the customer on a prior save — the excess becomes a customer
            // receivable instead of vanishing via the paid_amount clamp.
            $table->decimal('overpaid_amount', 12, 2)->default(0)->after('due_amount');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('product_exchanges', function (Blueprint $table) {
            $table->dropColumn('overpaid_amount');
        });
    }
};
