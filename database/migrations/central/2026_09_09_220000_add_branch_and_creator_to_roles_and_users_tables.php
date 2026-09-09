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
        Schema::table('roles', function (Blueprint $table) {
            $table->foreignId('branch_id')->nullable()->after('guard_name')->index();
            $table->foreignId('created_by_id')->nullable()->after('branch_id')->index();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('created_by_id')->nullable()->after('branch_id')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->dropColumn(['branch_id', 'created_by_id']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['created_by_id']);
        });
    }
};
