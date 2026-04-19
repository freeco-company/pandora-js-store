<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Aggregated daily counters for AI-related traffic.
 * One row per (date, bot_type, source). Upserted by the /track/ai-visit
 * endpoint; keeps storage bounded to ~20 rows/day max even under
 * heavy crawler load.
 *
 * source: "bot"  = crawler hit us directly (ClaudeBot, GPTBot, etc.)
 *         "user" = human visitor came from an AI site (chatgpt.com referer)
 * bot_type: normalized slug — claude, gpt, perplexity, google_ai,
 *           apple, bytedance, meta, amazon, common_crawl, other
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_visits_daily', function (Blueprint $table) {
            $table->id();
            $table->date('date')->index();
            $table->string('bot_type', 32);
            $table->string('source', 8); // "bot" | "user"
            $table->unsignedInteger('hits')->default(0);
            $table->string('last_path', 255)->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();

            $table->unique(['date', 'bot_type', 'source']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_visits_daily');
    }
};
