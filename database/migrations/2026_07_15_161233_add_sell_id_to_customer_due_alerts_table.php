<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customer_due_alerts', function (Blueprint $table) {
            $table->foreignId('sell_id')
                ->nullable()
                ->after('customer_id')
                ->constrained('sells')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('customer_due_alerts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('sell_id');
        });
    }
};
