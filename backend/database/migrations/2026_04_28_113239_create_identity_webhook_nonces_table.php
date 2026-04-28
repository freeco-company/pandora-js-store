<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * identity_webhook_nonces — 防 replay 用。
 *
 * Receiver 收到一個 webhook 後把 event_id 寫進來，UNIQUE constraint 保證
 * 同一個 event_id 第二次來時 INSERT 失敗 → 我們視為已處理 → 200 noop。
 *
 * 容量估算：< 10 customer × 變動頻率不高 + 7 天保留 = 永遠 < 100 row，
 * 不需要分區或特別 cleanup 策略。simple `received_at < now() - 7 days` 即可。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('identity_webhook_nonces', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->char('event_id', 36)->unique();
            $table->timestamp('received_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('identity_webhook_nonces');
    }
};
