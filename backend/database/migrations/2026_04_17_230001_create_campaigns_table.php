<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('campaigns', function (Blueprint $table) {
            $table->id();
            $table->string('name');                           // 活動名稱
            $table->string('slug')->unique();                 // URL slug
            $table->text('description')->nullable();          // 活動說明
            $table->string('image', 500)->nullable();         // 活動主圖
            $table->string('banner_image', 500)->nullable();  // 首頁倒數用橫幅
            $table->timestamp('start_at');                    // 活動開始
            $table->timestamp('end_at');                      // 活動結束
            $table->boolean('is_active')->default(true);      // 手動開關
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->index(['is_active', 'start_at', 'end_at']);
        });

        Schema::create('campaign_product', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->decimal('campaign_price', 10, 2)->nullable(); // 活動特價 (null = 用原價)
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['campaign_id', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('campaign_product');
        Schema::dropIfExists('campaigns');
    }
};
