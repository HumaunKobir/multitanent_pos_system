<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('branches', function (Blueprint $table) {
            if (! Schema::hasColumn('branches', 'custom_cycle_days')) {
                $table->integer('custom_cycle_days')->nullable()->after('subscription_last_paid_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('branches', function (Blueprint $table) {
            if (Schema::hasColumn('branches', 'custom_cycle_days')) {
                $table->dropColumn('custom_cycle_days');
            }
        });
    }
};
