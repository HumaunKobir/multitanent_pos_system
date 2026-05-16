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
        Schema::create('product_in_out_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('batch_id')->constrained('batches');
            $table->foreignId('branch_id')->nullable()->constrained('branches');
            $table->foreignId('product_id')->constrained('products');
            $table->integer('quantity');
            $table->integer('type');
            $table->integer('stock')->default(0);
            $table->text('remark')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_in_out_logs');
    }
};
