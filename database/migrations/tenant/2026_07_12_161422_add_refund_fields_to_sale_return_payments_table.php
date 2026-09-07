<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sale_return_payments', function (Blueprint $table) {
            $table->unsignedInteger('branch_id')->nullable()->index()->after('sale_return_id');
            $table->date('date')->nullable()->after('branch_id');
        });
    }

    public function down(): void
    {
        Schema::table('sale_return_payments', function (Blueprint $table) {
            $table->dropColumn(['branch_id', 'date']);
        });
    }
};
