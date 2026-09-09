<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('subscription_invoices')) {
            Schema::create('subscription_invoices', function (Blueprint $table) {
                $table->id();
                $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
                $table->string('invoice_number', 64)->unique();
                $table->date('billing_period_starts_at');
                $table->date('billing_period_ends_at');
                $table->date('due_date')->nullable();
                $table->decimal('subtotal', 14, 2)->default(0);
                $table->decimal('discount', 14, 2)->default(0);
                $table->decimal('total_amount', 14, 2)->default(0);
                $table->decimal('paid_amount', 14, 2)->default(0);
                $table->decimal('due_amount', 14, 2)->default(0);
                $table->string('status', 32)->default('unpaid'); // unpaid, partial, paid, overdue, void
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->index(['branch_id', 'status']);
                $table->index('due_date');
            });
        }

        if (! Schema::hasTable('subscription_payment_allocations')) {
            Schema::create('subscription_payment_allocations', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('branch_subscription_payment_id');
                $table->unsignedBigInteger('subscription_invoice_id');
                $table->decimal('amount', 14, 2);
                $table->timestamps();

                $table->foreign('branch_subscription_payment_id', 'fk_sub_alloc_payment_id')
                    ->references('id')->on('branch_subscription_payments')
                    ->cascadeOnDelete();

                $table->foreign('subscription_invoice_id', 'fk_sub_alloc_invoice_id')
                    ->references('id')->on('subscription_invoices')
                    ->cascadeOnDelete();

                $table->index(['branch_subscription_payment_id', 'subscription_invoice_id'], 'sub_pay_alloc_idx');
            });
        }

        if (! Schema::hasTable('branch_security_deposits')) {
            Schema::create('branch_security_deposits', function (Blueprint $table) {
                $table->id();
                $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
                $table->decimal('amount', 14, 2);
                $table->string('payment_method', 64);
                $table->unsignedBigInteger('payment_account_id')->nullable();
                $table->string('transaction_reference', 191)->nullable();
                $table->date('paid_at');
                $table->string('status', 32)->default('approved'); // approved, pending, refunded
                $table->text('notes')->nullable();
                $table->unsignedBigInteger('recorded_by_user_id')->nullable();
                $table->string('attachment_path', 500)->nullable();
                $table->timestamps();

                $table->index(['branch_id', 'status']);
            });
        }

        if (Schema::hasTable('branch_subscription_payments')) {
            if (! Schema::hasColumn('branch_subscription_payments', 'payment_type')) {
                Schema::table('branch_subscription_payments', function (Blueprint $table) {
                    $table->string('payment_type', 32)->default('subscription')->after('amount'); // subscription, advance, security_deposit
                });
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_payment_allocations');
        Schema::dropIfExists('subscription_invoices');
        Schema::dropIfExists('branch_security_deposits');

        if (Schema::hasTable('branch_subscription_payments') && Schema::hasColumn('branch_subscription_payments', 'payment_type')) {
            Schema::table('branch_subscription_payments', function (Blueprint $table) {
                $table->dropColumn('payment_type');
            });
        }
    }
};
