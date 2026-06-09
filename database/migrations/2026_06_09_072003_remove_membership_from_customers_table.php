<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn(['member_ship_id', 'is_membership']);
        });

        Schema::dropIfExists('member_ship_cards');
    }

    public function down(): void
    {
        Schema::create('member_ship_cards', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('image');
            $table->tinyInteger('status')->default(1);
            $table->timestamps();
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->foreignId('member_ship_id')->nullable()->after('branch_id');
            $table->boolean('is_membership')->default(0)->after('is_default');
        });
    }
};
