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
        Schema::create('article_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->enum('type', ['blog', 'news', 'brand', 'recommend']);
            $table->timestamps();
        });

        Schema::create('article_article_category', function (Blueprint $table) {
            $table->foreignId('article_id')->constrained()->cascadeOnDelete();
            $table->foreignId('article_category_id')->constrained()->cascadeOnDelete();
            $table->primary(['article_id', 'article_category_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('article_article_category');
        Schema::dropIfExists('article_categories');
    }
};
