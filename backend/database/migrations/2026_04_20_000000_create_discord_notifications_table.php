<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Log of outbound Discord webhook calls (DiscordNotifier::embed). Used by
 * the dashboard Discord activity widget to surface what's being pushed to
 * each channel and whether the webhook succeeded.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('discord_notifications', function (Blueprint $table) {
            $table->id();
            $table->string('channel', 32)->index(); // compliance / orders / ads / ads_strategy / unknown
            $table->string('title', 256);
            $table->boolean('success')->default(false);
            $table->unsignedSmallInteger('http_status')->nullable();
            $table->timestamp('sent_at')->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('discord_notifications');
    }
};
