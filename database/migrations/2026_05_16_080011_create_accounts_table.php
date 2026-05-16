<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accounts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('branch_id')->nullable();
            $table->string('number');
            $table->string('name');
            $table->text('account_info')->nullable();
            $table->decimal('balance', 14, 2)->default(0);
            $table->decimal('balance_in', 14, 2)->default(0);
            $table->decimal('balance_out', 14, 2)->default(0);
            $table->tinyInteger('status')->default(1);
            $table->timestamps();

            $table->foreign('branch_id')->references('id')->on('branches')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accounts');
    }
};
