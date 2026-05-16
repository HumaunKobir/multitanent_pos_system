<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('suppliers', function (Blueprint $table) {
            $table->decimal('balance', 14, 2)->default(0)->after('status');
            $table->decimal('balance_in', 14, 2)->default(0)->after('balance');
            $table->decimal('balance_out', 14, 2)->default(0)->after('balance_in');
        });
    }

    public function down(): void
    {
        Schema::table('suppliers', function (Blueprint $table) {
            $table->dropColumn(['balance', 'balance_in', 'balance_out']);
        });
    }
};
