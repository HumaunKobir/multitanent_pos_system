<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sell_products', function (Blueprint $table) {
            $table->decimal('free_quantity', 10, 2)->default(0)->after('quantity');
        });
    }

    public function down(): void
    {
        Schema::table('sell_products', function (Blueprint $table) {
            $table->dropColumn('free_quantity');
        });
    }
};
