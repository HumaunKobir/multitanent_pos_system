<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('chart_of_accounts', function (Blueprint $table) {
            $table->string('account_number')->nullable()->after('code');
        });
    }

    public function down(): void
    {
        Schema::table('chart_of_accounts', function (Blueprint $table) {
            $table->dropColumn('account_number');
        });
    }
};
