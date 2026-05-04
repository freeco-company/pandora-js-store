<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SPEC-cross-app-streak Phase 1.C — per-App 每日連續登入 streak（母艦 jerosse）。
 *
 * 與 customers.streak_days / last_active_date 的差異：那是 legacy 母艦 streak
 * （retention engine 用）；本表專收「每日打開 App 的登入 streak」，獨立成自
 * 己的 row 以便未來抽集團 master streak 時不破壞既有遊戲化邏輯。
 *
 * 一 customer 一 row（unique customer_id）；middleware 每 request 跑一次：
 *   - last_login_date == today      → no-op
 *   - last_login_date == yesterday  → +1
 *   - else                          → reset to 1
 *
 * 日期一律走 Asia/Taipei。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_daily_streaks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('current_streak')->default(0);
            $table->unsignedInteger('longest_streak')->default(0);
            $table->date('last_login_date')->nullable();
            $table->timestamps();

            $table->unique('customer_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_daily_streaks');
    }
};
