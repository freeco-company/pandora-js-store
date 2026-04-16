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
        Schema::create('articles', function (Blueprint $table) {
            $table->id();
            $table->string('title', 500);
            $table->string('slug', 500)->unique();
            $table->longText('content')->nullable();
            $table->text('excerpt')->nullable();
            $table->string('featured_image', 500)->nullable();
            $table->string('source_url', 500)->nullable();
            $table->enum('source_type', ['blog', 'news', 'brand', 'recommend']);
            $table->enum('status', ['draft', 'published'])->default('published');
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('articles');
    }
};
