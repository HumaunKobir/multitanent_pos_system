<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('coin_settings', function (Blueprint $table) {
            $table->unsignedInteger('expiry_value')->nullable()->after('max_redeem_percent');
            $table->string('expiry_unit')->nullable()->after('expiry_value');
        });
    }

    public function down(): void
    {
        Schema::table('coin_settings', function (Blueprint $table) {
            $table->dropColumn(['expiry_value', 'expiry_unit']);
        });
    }
};
