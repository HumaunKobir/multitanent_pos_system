<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tenant databases keep a local branches row so existing foreign keys
 * (customers.branch_id, products.branch_id, …) remain valid. The source of
 * truth for branch registry remains the central database.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! config('tenancy.enabled') || Schema::hasTable('branches')) {
            return;
        }

        Schema::create('branches', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('phone')->nullable();
            $table->text('address')->nullable();
            $table->tinyInteger('status')->default(1);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        if (! config('tenancy.enabled')) {
            return;
        }

        Schema::dropIfExists('branches');
    }
};
