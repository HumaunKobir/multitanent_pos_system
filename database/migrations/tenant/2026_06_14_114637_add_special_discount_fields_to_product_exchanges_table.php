<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_exchanges', function (Blueprint $table) {
            $table->foreignId('special_discount_id')
                ->nullable()
                ->after('gross_amount')
                ->constrained('special_discounts')
                ->nullOnDelete();
            $table->decimal('special_discount_amount', 12, 2)->default(0)->after('special_discount_id');
        });
    }

    public function down(): void
    {
        Schema::table('product_exchanges', function (Blueprint $table) {
            $table->dropConstrainedForeignId('special_discount_id');
            $table->dropColumn('special_discount_amount');
        });
    }
};
