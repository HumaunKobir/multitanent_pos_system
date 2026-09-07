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
            $table->foreignId('source_branch_id')
                ->nullable()
                ->after('product_group_id')
                ->constrained('branches')
                ->nullOnDelete();
            $table->timestamp('received_at')->nullable()->after('source_branch_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropConstrainedForeignId('source_branch_id');
            $table->dropColumn('received_at');
        });
    }
};
