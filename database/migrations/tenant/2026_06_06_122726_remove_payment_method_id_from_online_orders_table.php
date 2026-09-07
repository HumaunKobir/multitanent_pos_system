<?php

use App\Enums\CommonStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('online_orders', 'payment_method_id')) {
            Schema::table('online_orders', function (Blueprint $table) {
                $table->dropForeign(['payment_method_id']);
                $table->dropColumn('payment_method_id');
            });
        }

        Schema::dropIfExists('payment_methods');
    }

    public function down(): void
    {
        Schema::create('payment_methods', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->tinyInteger('status')->default(CommonStatus::Active);
            $table->timestamps();
        });

        Schema::table('online_orders', function (Blueprint $table) {
            $table->foreignId('payment_method_id')->nullable()->after('address')->constrained('payment_methods');
        });
    }
};
