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
        Schema::table('products', function (Blueprint $table) {
            $table->foreignId('color_id')->nullable()->after('warranty_id')->constrained('colors')->nullOnDelete();
            $table->foreignId('size_id')->nullable()->after('color_id')->constrained('sizes')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropConstrainedForeignId('size_id');
            $table->dropConstrainedForeignId('color_id');
        });
    }
};
