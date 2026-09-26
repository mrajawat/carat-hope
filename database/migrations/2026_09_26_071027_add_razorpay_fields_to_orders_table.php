<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('currency', 3)->default('INR')->after('total_amount');
            $table->string('razorpay_order_id')->nullable()->unique()->after('payment_method');
            $table->string('razorpay_payment_id')->nullable()->after('razorpay_order_id');
            $table->timestamp('paid_at')->nullable()->after('razorpay_payment_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropUnique(['razorpay_order_id']);
            $table->dropColumn(['currency', 'razorpay_order_id', 'razorpay_payment_id', 'paid_at']);
        });
    }
};
