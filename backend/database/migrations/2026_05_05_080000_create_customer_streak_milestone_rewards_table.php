<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SPEC-streak-milestone-rewards (mother) — track which milestones have been
 * unlocked per customer so we can:
 *   1. Idempotently skip re-issuing rewards on the same milestone
 *   2. Power admin reporting / streak ROI analytics
 *   3. Link a personalised coupon to its milestone for traceability
 *
 * `coupon_id` is nullable because not every milestone issues a coupon
 * (1 / 3 / 7 / 14 / 30 are achievement-only; 21 / 60 / 100 issue coupons).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_streak_milestone_rewards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('streak_days'); // 1/3/7/14/21/30/60/100
            $table->foreignId('coupon_id')->nullable()->constrained('coupons')->nullOnDelete();
            $table->json('achievements_awarded')->nullable(); // list of achievement codes granted
            $table->timestamp('unlocked_at');
            $table->timestamps();

            $table->unique(['customer_id', 'streak_days']);
            $table->index('streak_days');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_streak_milestone_rewards');
    }
};
