<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * COD「需 LINE 確認後才出貨」流程：
 *  - status enum 增 `pending_confirmation`
 *  - confirmed_at：客人在 LINE 點「確認出貨」的時間
 *  - line_user_id：客人為此單綁定的 LINE userId（可能與 customer.line_id 不同）
 *  - confirmation_token：random hex，防止 LINE postback 被偽造（webhook 驗 token）
 *  - confirmation_reminder_sent_at：提醒推播去重
 */
return new class extends Migration
{
    public function up(): void
    {
        $driver = DB::connection()->getDriverName();
        if ($driver === 'mysql' || $driver === 'mariadb') {
            DB::statement(
                "ALTER TABLE orders MODIFY status ENUM("
                    ."'pending','pending_confirmation','processing','shipped','completed','cancelled','refunded','cod_no_pickup'"
                .") NOT NULL DEFAULT 'pending'"
            );
        }

        Schema::table('orders', function (Blueprint $table) {
            $table->timestamp('confirmed_at')->nullable()->after('shipped_at');
            $table->string('line_user_id', 64)->nullable()->after('confirmed_at');
            $table->string('confirmation_token', 64)->nullable()->after('line_user_id');
            $table->timestamp('confirmation_reminder_sent_at')->nullable()->after('confirmation_token');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['confirmed_at', 'line_user_id', 'confirmation_token', 'confirmation_reminder_sent_at']);
        });

        $driver = DB::connection()->getDriverName();
        if ($driver === 'mysql' || $driver === 'mariadb') {
            DB::statement(
                "ALTER TABLE orders MODIFY status ENUM("
                    ."'pending','processing','shipped','completed','cancelled','refunded','cod_no_pickup'"
                .") NOT NULL DEFAULT 'pending'"
            );
        }
    }
};
