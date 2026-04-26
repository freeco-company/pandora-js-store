<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_visits_by_path', function (Blueprint $table) {
            $table->id();
            $table->date('date');
            $table->string('path', 255);
            $table->string('bot_type', 32);
            $table->unsignedInteger('hits')->default(0);
            $table->dateTime('last_seen_at');
            $table->timestamps();

            $table->unique(['date', 'path', 'bot_type'], 'aivp_unique');
            $table->index(['date', 'path'], 'aivp_date_path');
            $table->index('path');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_visits_by_path');
    }
};
