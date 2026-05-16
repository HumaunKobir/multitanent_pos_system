<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->unsignedBigInteger('branch_id')->nullable()->after('address');
            $table->unsignedBigInteger('group_id')->nullable()->after('branch_id');
            $table->string('api_token', 80)->nullable()->unique()->after('remember_token');

            $table->foreign('branch_id')->references('id')->on('branches')->nullOnDelete();
            $table->foreign('group_id')->references('id')->on('groups')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropForeign(['branch_id', 'group_id']);
            $table->dropColumn(['branch_id', 'group_id', 'api_token']);
        });
    }
};
