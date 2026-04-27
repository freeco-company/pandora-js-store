<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 評論提醒第二輪 idempotency。
 *   - review_reminder_sent_at（已存在）：第一次提醒（訂單完成後 7 天）
 *   - review_reminder_2_sent_at（新增）：第二次（最後）提醒（訂單完成後 14 天）
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->timestamp('review_reminder_2_sent_at')->nullable()->after('review_reminder_sent_at');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('review_reminder_2_sent_at');
        });
    }
};
