<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 2 (ADR-007 §2.4 / pandora-js-store#11)：把 platform 的 UUID v7
 * 鎖到 customer 上，供 webhook upsert 使用。
 *
 * - NULLABLE：既有 < 10 客戶在 backfill command 跑完前是 null。
 * - UNIQUE：platform 是 single source of truth，同一個 uuid 不該對應多個 customer。
 * - 不寫 FK：跨 service，FK 不可行；改靠 unique constraint + reconcile job 保資料正確。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->char('pandora_user_uuid', 36)->nullable()->unique()->after('id');
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropUnique(['pandora_user_uuid']);
            $table->dropColumn('pandora_user_uuid');
        });
    }
};
