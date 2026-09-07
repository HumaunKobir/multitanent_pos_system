<?php

use App\Enums\PurchaseReceivedPayment;
use App\Enums\PurchaseType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->nullable()->constrained('branches');
            $table->foreignId('supplier_id')->nullable()->constrained('suppliers')->restrictOnDelete();
            $table->unsignedBigInteger('parent_purchase_id')->nullable();
            $table->date('date');
            $table->decimal('gross_amount', 12, 2)->default(0);
            $table->decimal('discount', 12, 2)->default(0);
            $table->decimal('vat', 12, 2)->default(0);
            $table->decimal('paid_amount', 12, 2)->default(0);
            $table->decimal('due_amount', 12, 2)->default(0);
            $table->decimal('demarace', 12, 2)->default(0);
            $table->tinyInteger('purchase_type')->default(PurchaseType::Purchase->value);
            $table->tinyInteger('payment_type')->default(PurchaseReceivedPayment::Cash->value);
            $table->string('serial')->nullable();
            $table->text('comment')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchases');
    }
};
