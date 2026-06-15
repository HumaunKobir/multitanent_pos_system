<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('products')
            ->select('id', 'color_id', 'size_id')
            ->where(function ($query) {
                $query->whereNotNull('color_id')->orWhereNotNull('size_id');
            })
            ->orderBy('id')
            ->each(function (object $product): void {
                DB::table('products')
                    ->where('id', $product->id)
                    ->update([
                        'colors' => $product->color_id ? json_encode([(int) $product->color_id]) : null,
                        'sizes' => $product->size_id ? json_encode([(int) $product->size_id]) : null,
                    ]);
            });

        Schema::table('products', function (Blueprint $table) {
            $table->dropConstrainedForeignId('size_id');
            $table->dropConstrainedForeignId('color_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->foreignId('color_id')->nullable()->after('warranty_id')->constrained('colors')->nullOnDelete();
            $table->foreignId('size_id')->nullable()->after('color_id')->constrained('sizes')->nullOnDelete();
        });

        DB::table('products')
            ->select('id', 'colors', 'sizes')
            ->where(function ($query) {
                $query->whereNotNull('colors')->orWhereNotNull('sizes');
            })
            ->orderBy('id')
            ->each(function (object $product): void {
                $colors = json_decode($product->colors ?? '[]', true) ?: [];
                $sizes = json_decode($product->sizes ?? '[]', true) ?: [];

                DB::table('products')
                    ->where('id', $product->id)
                    ->update([
                        'color_id' => $colors[0] ?? null,
                        'size_id' => $sizes[0] ?? null,
                    ]);
            });
    }
};
