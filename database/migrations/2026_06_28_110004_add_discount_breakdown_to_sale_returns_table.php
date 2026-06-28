<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Persist the invoice discount and round off breakdown so editing a sale return restores
     * the exact values the user entered, rather than re-deriving them from the parent sale.
     */
    public function up(): void
    {
        // Nullable on purpose: a NULL marks a legacy return saved before this breakdown existed,
        // so the edit screen can fall back to the parent sale's defaults. Returns saved from now
        // on always write explicit values (including 0), which are then restored exactly.
        Schema::table('sale_returns', function (Blueprint $table) {
            $table->string('invoice_discount_type', 16)->nullable()->after('discount_amount');
            $table->decimal('invoice_discount_value', 12, 2)->nullable()->after('invoice_discount_type');
            $table->decimal('round_off_amount', 12, 2)->nullable()->after('invoice_discount_value');
        });
    }

    public function down(): void
    {
        Schema::table('sale_returns', function (Blueprint $table) {
            $table->dropColumn(['invoice_discount_type', 'invoice_discount_value', 'round_off_amount']);
        });
    }
};
