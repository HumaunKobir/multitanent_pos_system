<?php

use App\Enums\DiscountType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('special_discounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->string('name');
            $table->decimal('min_amount', 12, 2);
            $table->decimal('max_amount', 12, 2)->nullable();
            $table->string('discount_type')->default(DiscountType::Flat->value);
            $table->decimal('discount_value', 12, 2);
            $table->boolean('status')->default(true);
            $table->timestamps();

            $table->index(['branch_id', 'status', 'min_amount']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('special_discounts');
    }
};
