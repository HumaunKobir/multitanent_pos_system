<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Minimal users table so tenant FKs (received_by_user_id, created_by, …) can
 * reference staff who live on the central database.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! config('tenancy.enabled') || Schema::hasTable('users')) {
            return;
        }

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        if (! config('tenancy.enabled')) {
            return;
        }

        Schema::dropIfExists('users');
    }
};
