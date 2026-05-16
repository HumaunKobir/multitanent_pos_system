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
        Schema::create('product_sections', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('button_text')->nullable();
            $table->json('images')->nullable();
            $table->json('items')->nullable();
            $table->tinyInteger('block_per_line')->default(4);
            $table->tinyInteger('layout_type')->default(1); // 1=Image_Block, 2=Slider
            $table->tinyInteger('block_type')->default(2);  // 1=Image, 2=Item
            $table->tinyInteger('status')->default(1);
            $table->integer('serial')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_sections');
    }
};
