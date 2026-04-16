<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Stock-back-in-stock subscriptions ("到貨通知").
 * When a customer clicks "通知我" on an out-of-stock product, we drop a row.
 * A scheduled job scans products that flipped back to in-stock and mails the subscribers.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('email');
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('notified_at')->nullable();
            $table->timestamps();

            // Prevent duplicate signups for same product+email
            $table->unique(['product_id', 'email']);
            $table->index('notified_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_notifications');
    }
};
