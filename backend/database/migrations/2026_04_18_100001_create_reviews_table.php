<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->tinyInteger('rating')->unsigned(); // 1-5
            $table->text('content')->nullable();
            $table->string('reviewer_name'); // masked name like 王*明
            $table->boolean('is_verified_purchase')->default(false);
            $table->boolean('is_seeded')->default(false); // fake seed data
            $table->boolean('is_visible')->default(true);
            $table->timestamp('auto_review_reminder_sent_at')->nullable();
            $table->timestamps();

            // A customer can only review a product once per order
            $table->unique(['product_id', 'customer_id', 'order_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reviews');
    }
};
