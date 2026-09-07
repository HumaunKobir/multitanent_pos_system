<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('business_session_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_session_id')->nullable()->index();
            $table->foreignId('user_id')->index();
            $table->string('action');
            $table->json('details')->nullable();
            $table->timestamps();

            $table->index('action');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('business_session_audit_logs');
    }
};
