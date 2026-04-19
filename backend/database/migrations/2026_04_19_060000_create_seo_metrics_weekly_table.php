<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seo_metrics_weekly', function (Blueprint $table) {
            $table->id();
            $table->date('measured_on');
            $table->string('url', 500);
            $table->string('label', 100);              // e.g. 'home', 'product_detail', 'article_detail'
            $table->string('strategy', 20);            // 'mobile' | 'desktop'
            $table->unsignedTinyInteger('perf_score')->nullable();   // 0-100
            $table->unsignedSmallInteger('lcp_ms')->nullable();
            $table->unsignedSmallInteger('cls_x1000')->nullable();   // CLS × 1000 (e.g. 0.072 → 72)
            $table->unsignedSmallInteger('tbt_ms')->nullable();
            $table->unsignedSmallInteger('inp_ms')->nullable();
            $table->json('opportunities')->nullable(); // top issues for narrative
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['measured_on', 'label', 'strategy']);
            $table->index('measured_on');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seo_metrics_weekly');
    }
};
