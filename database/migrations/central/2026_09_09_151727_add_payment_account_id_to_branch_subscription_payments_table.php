<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('branch_subscription_payments', function (Blueprint $table) {
            $table->unsignedBigInteger('payment_account_id')->nullable()->after('payment_method');
        });
    }

    public function down(): void
    {
        Schema::table('branch_subscription_payments', function (Blueprint $table) {
            $table->dropColumn('payment_account_id');
        });
    }
};
