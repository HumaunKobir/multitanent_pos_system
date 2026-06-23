<?php

use App\Enums\PromotionScope;
use App\Enums\PromotionType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('promotions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('scope')->default(PromotionScope::Product->value);
            $table->string('type')->default(PromotionType::Percent->value);
            $table->decimal('discount_value', 12, 2)->default(0);
            $table->decimal('fixed_price', 12, 2)->nullable();
            $table->unsignedInteger('buy_qty')->nullable();
            $table->unsignedInteger('get_qty')->nullable();
            $table->decimal('get_discount_percent', 5, 2)->nullable();
            $table->json('bundle_product_ids')->nullable();
            $table->decimal('min_qty', 10, 2)->nullable();
            $table->decimal('max_discount_amount', 12, 2)->nullable();
            $table->dateTime('starts_at')->nullable();
            $table->dateTime('ends_at')->nullable();
            $table->boolean('status')->default(true);
            $table->integer('priority')->default(0);
            $table->boolean('stack_with_product_discount')->default(true);
            $table->boolean('stack_with_manual_line_discount')->default(true);
            $table->boolean('stack_with_invoice_discount')->default(true);
            $table->boolean('stack_with_special_discount')->default(true);
            $table->boolean('exclusive')->default(false);
            $table->timestamps();

            $table->index(['branch_id', 'status', 'starts_at', 'ends_at', 'scope', 'priority'], 'promotions_branch_schedule_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('promotions');
    }
};
