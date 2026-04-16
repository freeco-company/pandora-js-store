<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Referral program (EXP-based, not money):
 *  - Each customer gets a unique `referral_code` (auto on first save).
 *  - New sign-ups that pass ?ref=CODE get linked via `referred_by_customer_id`.
 *  - When the referred customer's first order completes, both sides earn
 *    the `first_referral` / `first_referred` achievement (= EXP via existing
 *    streak + achievement gamification).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->string('referral_code', 16)->nullable()->unique()->after('membership_level');
            $table->foreignId('referred_by_customer_id')->nullable()->after('referral_code')
                ->constrained('customers')->nullOnDelete();
            $table->boolean('referral_reward_granted')->default(false)->after('referred_by_customer_id');
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropForeign(['referred_by_customer_id']);
            $table->dropColumn(['referral_code', 'referred_by_customer_id', 'referral_reward_granted']);
        });
    }
};
