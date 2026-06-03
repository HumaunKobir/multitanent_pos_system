<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->boolean('debit_decrease')->default(false)->after('credit_account_id');
            $table->boolean('credit_decrease')->default(false)->after('debit_decrease');
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropColumn(['debit_decrease', 'credit_decrease']);
        });
    }
};
