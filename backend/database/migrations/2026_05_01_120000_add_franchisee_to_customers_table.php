<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 母艦端「加盟夥伴」狀態欄位。
 *
 * 母艦是「誰是 加盟夥伴」的 source of truth，狀態變動時：
 *   1. CustomerObserver 觀察 is_franchisee 變化
 *   2. FranchiseEventPublisher 寫一筆 franchise_outbox_events
 *   3. SendFranchiseWebhookJob 簽 HMAC POST 給 朵朵 (pandora-meal) 的
 *      /api/internal/franchisee/webhook，朵朵收到後 unlock FP-gated content
 *      (fp_crown / fp_chef / fp_apron_premium / fp_recipe / FP food)。
 *
 * verified_at 用途：朵朵端要追溯「何時被認證為加盟」，作為成就 / activity log 時間軸。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->boolean('is_franchisee')->default(false)->after('is_vip');
            $table->timestamp('franchisee_verified_at')->nullable()->after('is_franchisee');

            $table->index('is_franchisee');
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropIndex(['is_franchisee']);
            $table->dropColumn(['is_franchisee', 'franchisee_verified_at']);
        });
    }
};
