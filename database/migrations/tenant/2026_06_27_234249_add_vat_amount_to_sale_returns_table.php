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
        Schema::table('sale_returns', function (Blueprint $table) {
            $table->decimal('vat_amount', 12, 2)->default(0)->after('gross_amount');
            $table->decimal('vat_percent', 8, 2)->default(0)->after('vat_amount');
        });
    }

    public function down(): void
    {
        Schema::table('sale_returns', function (Blueprint $table) {
            $table->dropColumn(['vat_amount', 'vat_percent']);
        });
    }
};
