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
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->longText('description')->nullable();
            $table->text('short_description')->nullable();
            $table->decimal('price', 10, 2);
            $table->decimal('combo_price', 10, 2)->nullable();
            $table->decimal('vip_price', 10, 2)->nullable();
            $table->decimal('sale_price', 10, 2)->nullable();
            $table->string('sku', 100)->nullable();
            $table->integer('stock_quantity')->default(0);
            $table->enum('stock_status', ['instock', 'outofstock'])->default('instock');
            $table->decimal('weight', 8, 2)->nullable();
            $table->string('image', 500)->nullable();
            $table->json('gallery')->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->integer('wp_id')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
