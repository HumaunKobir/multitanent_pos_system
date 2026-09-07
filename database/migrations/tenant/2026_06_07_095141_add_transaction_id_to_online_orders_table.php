<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('online_orders', function (Blueprint $table) {
            $table->string('transaction_id')->nullable()->unique()->after('payment_method');
        });
    }

    public function down(): void
    {
        Schema::table('online_orders', function (Blueprint $table) {
            $table->dropUnique(['transaction_id']);
            $table->dropColumn('transaction_id');
        });
    }
};
