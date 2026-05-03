<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 多段提醒：保留既有 confirmation_reminder_sent_at（最後一次寄出時間，
 * 給 audit / Filament 顯示用），新增 confirmation_reminder_stage 表示
 * 已送至第幾段（0 = 還沒送）。
 *
 * - 信用卡：5 段（1h / 6h / 24h / 72h / 144h）→ 168h 自動取消
 * - 銀行轉帳：2 段（3h / 24h）→ 48h 自動取消
 * - COD pending_confirmation：2 段（3h / 24h）→ 48h 自動取消
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->unsignedTinyInteger('confirmation_reminder_stage')
                ->default(0)
                ->after('confirmation_reminder_sent_at');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('confirmation_reminder_stage');
        });
    }
};
