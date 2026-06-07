<?php

use App\Enums\CommonStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chart_of_accounts', function (Blueprint $table) {
            $table->id();
            $table->nullableMorphs('source');
            $table->foreignId('parent_id')->nullable()->constrained('chart_of_accounts')->nullOnDelete();
            $table->string('code');
            $table->string('name');
            $table->unsignedTinyInteger('type')->index();
            $table->decimal('current_balance', 15, 2)->default(0);
            $table->text('description')->nullable();
            $table->tinyInteger('status')->default(CommonStatus::Active);
            $table->boolean('is_system')->default(false);
            $table->softDeletes();
            $table->timestamps();

        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chart_of_accounts');
    }
};
