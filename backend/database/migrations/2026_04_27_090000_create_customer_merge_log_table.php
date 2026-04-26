<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * customer_merge_log — audit trail of every customer merge.
 *
 * 為什麼留 log 而不是 soft-delete 被合併的 customer：
 *   - 合併後對方 row 完全砍掉（避免殭屍 row 出現在 query / Filament 列表）
 *   - 但要保留「這個 customer 是被合併到那個」的歷史，方便：
 *     - 客服查詢「我之前的訂單呢」
 *     - 萬一錯誤合併（家人帳號被合進來）能查出來手動還原
 *     - dedupe 跳過已合併過的對
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_merge_log', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('surviving_customer_id');
            $table->unsignedBigInteger('absorbed_customer_id');
            $table->string('absorbed_email', 255)->nullable();
            $table->string('absorbed_phone', 32)->nullable();
            $table->string('absorbed_google_id', 64)->nullable();
            $table->string('absorbed_line_id', 64)->nullable();
            // 合併原因：'auto:phone+placeholder' / 'manual:filament' / 'auto:email-match' 等
            $table->string('reason', 64);
            // 合併前 surviving 與 absorbed 的訂單 / 消費（debug / 還原用）
            $table->json('snapshot')->nullable();
            $table->unsignedBigInteger('actor_admin_id')->nullable(); // null = 系統自動
            $table->timestamps();

            $table->index('surviving_customer_id');
            $table->index('absorbed_customer_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_merge_log');
    }
};
