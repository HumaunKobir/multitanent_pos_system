<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_section_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_section_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('product_id')->nullable();
            // 1=Image, 2=Item
            $table->tinyInteger('block_type')->default(2);
            $table->string('image')->nullable();
            $table->string('link')->nullable();
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->foreign('product_id')->references('id')->on('products')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_section_items');
    }
};
