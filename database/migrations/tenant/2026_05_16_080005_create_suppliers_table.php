<?php

use App\Enums\CommonStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->nullable()->constrained('branches');
            $table->string('name');
            $table->string('phone');
            $table->string('company_name')->nullable();
            $table->text('address')->nullable();
            $table->decimal('balance', 14, 2)->default(0);
            $table->tinyInteger('status')->default(CommonStatus::Active);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('suppliers');
    }
};
