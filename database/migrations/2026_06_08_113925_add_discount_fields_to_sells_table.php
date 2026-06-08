<?php

use App\Enums\DiscountType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sells', function (Blueprint $table) {
            $table->string('discount_type')->default(DiscountType::Flat->value)->after('discount');
            $table->decimal('discount_value', 12, 2)->nullable()->after('discount_type');
            $table->foreignId('special_discount_id')->nullable()->after('discount_value')->constrained('special_discounts')->nullOnDelete();
            $table->decimal('special_discount_amount', 12, 2)->default(0)->after('special_discount_id');
        });
    }

    public function down(): void
    {
        Schema::table('sells', function (Blueprint $table) {
            $table->dropConstrainedForeignId('special_discount_id');
            $table->dropColumn(['discount_type', 'discount_value', 'special_discount_amount']);
        });
    }
};
