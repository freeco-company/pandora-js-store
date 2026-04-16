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
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_number', 50)->unique();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('status', ['pending', 'processing', 'completed', 'cancelled', 'refunded'])->default('pending');
            $table->enum('pricing_tier', ['regular', 'combo', 'vip']);
            $table->decimal('subtotal', 10, 2);
            $table->decimal('shipping_fee', 10, 2)->default(0);
            $table->decimal('total', 10, 2);
            $table->string('payment_method', 50)->nullable();
            $table->string('payment_status', 50)->nullable();
            $table->string('ecpay_trade_no', 50)->nullable();
            $table->string('shipping_method', 50)->nullable();
            $table->string('shipping_name')->nullable();
            $table->string('shipping_phone', 50)->nullable();
            $table->text('shipping_address')->nullable();
            $table->string('shipping_store_id', 50)->nullable();
            $table->string('shipping_store_name')->nullable();
            $table->text('note')->nullable();
            $table->integer('wp_order_id')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
