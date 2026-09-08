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
        Schema::table('branch_subscription_payments', function (Blueprint $table) {
            $table->string('status', 30)->default('approved')->after('payment_method');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('branch_subscription_payments', function (Blueprint $table) {
            $table->dropColumn('status');
        });
    }
};
