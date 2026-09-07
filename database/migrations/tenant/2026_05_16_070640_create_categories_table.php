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
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('branch_id')->nullable();
            $table->uuid('catalog_group_id')->nullable();
            $table->string('name');
            $table->string('slug')->nullable();
            $table->string('image')->nullable();
            $table->tinyInteger('status')->default(1);
            $table->timestamps();

            $table->index('branch_id');
            $table->index('catalog_group_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('categories');
    }
};
