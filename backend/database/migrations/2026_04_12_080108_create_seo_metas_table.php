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
        Schema::create('seo_metas', function (Blueprint $table) {
            $table->id();
            $table->string('metable_type');
            $table->unsignedBigInteger('metable_id');
            $table->string('title')->nullable();
            $table->string('description', 500)->nullable();
            $table->string('focus_keyword')->nullable();
            $table->string('og_image', 500)->nullable();
            $table->string('canonical_url', 500)->nullable();
            $table->string('robots', 100)->default('index,follow');
            $table->json('schema_json')->nullable();
            $table->timestamps();
            $table->index(['metable_type', 'metable_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('seo_metas');
    }
};
