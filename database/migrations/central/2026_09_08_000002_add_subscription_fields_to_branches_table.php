<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('branches', function (Blueprint $table) {
            $table->string('subscription_plan')->default('standard')->after('status');
            $table->string('subscription_status')->default('active')->after('subscription_plan');
            $table->decimal('subscription_fee', 12, 2)->nullable()->after('subscription_status');
            $table->date('subscription_starts_at')->nullable()->after('subscription_fee');
            $table->date('subscription_expires_at')->nullable()->after('subscription_starts_at');
            $table->date('subscription_last_paid_at')->nullable()->after('subscription_expires_at');
            $table->integer('custom_grace_period_days')->nullable()->after('subscription_last_paid_at');
            $table->integer('custom_warning_days')->nullable()->after('custom_grace_period_days');
            $table->string('custom_overdue_action')->nullable()->after('custom_warning_days');
            $table->text('subscription_notes')->nullable()->after('custom_overdue_action');
        });
    }

    public function down(): void
    {
        Schema::table('branches', function (Blueprint $table) {
            $table->dropColumn([
                'subscription_plan',
                'subscription_status',
                'subscription_fee',
                'subscription_starts_at',
                'subscription_expires_at',
                'subscription_last_paid_at',
                'custom_grace_period_days',
                'custom_warning_days',
                'custom_overdue_action',
                'subscription_notes',
            ]);
        });
    }
};
