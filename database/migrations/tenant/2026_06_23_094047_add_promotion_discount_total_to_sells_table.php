<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sells', function (Blueprint $table) {
            $table->decimal('promotion_discount_total', 12, 2)->default(0)->after('special_discount_amount');
        });
    }

    public function down(): void
    {
        Schema::table('sells', function (Blueprint $table) {
            $table->dropColumn('promotion_discount_total');
        });
    }
};
