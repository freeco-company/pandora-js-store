<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Replay-protection table for inbound gamification webhooks (ADR-009 Phase B).
 *
 * py-service's outbox dispatcher retries on transient failures; we INSERT
 * the event_id on receipt and short-circuit duplicates with 200 (since
 * publisher-side already considers the event delivered).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gamification_webhook_nonces', function (Blueprint $table) {
            $table->string('event_id', 191)->primary();
            $table->timestamp('received_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gamification_webhook_nonces');
    }
};
