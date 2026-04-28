<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * outbox_identity_events — 母艦端 shadow-mirror 待送 events 暫存。
 *
 * 設計（ADR-001 §4.1 Step 1）：
 *   - Customer / CustomerIdentity 寫入 → 同 transaction 內塞一筆到 outbox
 *   - Queue worker (ProcessIdentityOutbox) 拉 pending → 打 platform internal
 *     endpoint → 成功標 sent_at / 失敗 retry_count++ + next_retry_at
 *   - 失敗策略：5xx 重試（exponential backoff），4xx 標 dead_letter，>= 5
 *     次徹底失敗也標 dead_letter
 *
 * shadow mode = 寫失敗不該炸主流程，只記 outbox + 日後 retry。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('outbox_identity_events', function (Blueprint $table) {
            $table->id();

            // 'customer.upserted' / 'customer.identity_added' / 'customer.deleted' / 'consent.changed'
            $table->string('event_type', 64);

            // 主體 customer id（debug + dedupe 用）
            $table->unsignedBigInteger('customer_id')->nullable();

            $table->json('payload');

            // 'pending' / 'sent' / 'dead_letter'
            $table->string('status', 16)->default('pending');

            $table->unsignedInteger('retry_count')->default(0);
            $table->timestamp('next_retry_at')->nullable();

            $table->timestamp('sent_at')->nullable();
            $table->text('last_error')->nullable();

            $table->timestamps();

            $table->index(['status', 'next_retry_at']);
            $table->index('customer_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('outbox_identity_events');
    }
};
