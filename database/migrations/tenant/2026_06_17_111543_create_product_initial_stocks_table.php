<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('product_initial_stocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('product_variation_id')->nullable()->constrained('product_variations')->cascadeOnDelete();
            $table->foreignId('batch_id')->nullable()->constrained('batches')->nullOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('branches');
            $table->unsignedInteger('quantity')->default(0);
            $table->decimal('unit_cost', 10, 2)->default(0);
            $table->timestamps();

            $table->index(['product_id', 'product_variation_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_initial_stocks');
    }
};
