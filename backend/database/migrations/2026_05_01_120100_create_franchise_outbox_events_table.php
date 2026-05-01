<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 母艦 → 朵朵 (pandora-meal) franchise webhook 的 outbox table。
 *
 * 用 outbox pattern 而不是直接 HTTP：observer 寫到 customer 同 transaction，
 * 即便 朵朵 端離線、HTTP 失敗，event 不會掉。Worker 拉 pending → 簽 HMAC →
 * POST → 成功標 dispatched_at；失敗 attempts++、exponential backoff，5 次
 * 後留在 table 等人工處理。
 *
 * 與 outbox_identity_events 不同 channel：
 *   - identity outbox 寫 PII upsert（給 Pandora Core）
 *   - franchise outbox 只送「身份標誌」變化（給朵朵 unlock content）
 * 兩者用不同 secret、不同 receiver、不同 dead-letter handling。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('franchise_outbox_events', function (Blueprint $table) {
            $table->id();

            // UUID v7 — 也作為 webhook 端 nonce dedupe key
            $table->char('event_id', 36)->unique();

            // 'franchisee.activated' / 'franchisee.deactivated'
            $table->string('event_type', 64);

            $table->json('payload');

            // 主體 customer reference (debug + manual triage 用)
            $table->unsignedBigInteger('customer_id')->nullable();
            // 朵朵端 user lookup key — pandora_user_uuid 優先 / email fallback
            $table->string('target_uuid', 64)->nullable();
            $table->string('target_email')->nullable();

            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('dispatched_at')->nullable();

            $table->unsignedSmallInteger('attempts')->default(0);
            $table->timestamp('next_retry_at')->nullable();
            $table->string('last_status_code', 8)->nullable();
            $table->text('last_error')->nullable();

            // pull pending (dispatched_at IS NULL) for sweeper
            $table->index(['dispatched_at', 'next_retry_at']);
            $table->index('customer_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('franchise_outbox_events');
    }
};
