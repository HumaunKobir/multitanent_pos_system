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
        Schema::table('stock_distributions', function (Blueprint $table) {
            $table->timestamp('return_sent_at')->nullable()->after('received_by_user_id');
            $table->foreignId('return_sent_by_user_id')->nullable()->after('return_sent_at')->constrained('users')->nullOnDelete();
            $table->timestamp('return_received_at')->nullable()->after('return_sent_by_user_id');
            $table->foreignId('return_received_by_user_id')->nullable()->after('return_received_at')->constrained('users')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stock_distributions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('return_sent_by_user_id');
            $table->dropConstrainedForeignId('return_received_by_user_id');
            $table->dropColumn(['return_sent_at', 'return_received_at']);
        });
    }
};
