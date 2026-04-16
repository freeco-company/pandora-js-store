<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('popups', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('image')->nullable();
            $table->string('link')->nullable();
            $table->text('content')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->enum('display_frequency', ['once', 'every_visit', 'once_per_day'])->default('once');
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('popups');
    }
};
