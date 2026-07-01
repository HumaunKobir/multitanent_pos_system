<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('business_sessions', function (Blueprint $table) {
            $table->id();
            $table->string('session_number')->unique();
            $table->date('session_date');
            $table->foreignId('branch_id')->nullable()->index();
            $table->foreignId('started_by_user_id')->index();
            $table->foreignId('closed_by_user_id')->nullable()->index();
            $table->dateTime('started_at');
            $table->dateTime('closed_at')->nullable();
            $table->unsignedTinyInteger('opening_method');
            $table->unsignedTinyInteger('status');
            $table->decimal('total_opening_balance', 15, 2)->default(0);
            $table->decimal('total_closing_balance', 15, 2)->nullable();
            $table->json('report_snapshot')->nullable();
            $table->timestamps();

            $table->index(['branch_id', 'status']);
            $table->index('session_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('business_sessions');
    }
};
