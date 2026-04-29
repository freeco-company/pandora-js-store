<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ADR-009 Phase B inbound — mirror columns on customers so 母艦 can reflect
 * cross-app gamification state pushed from py-service via webhook.
 *
 * Source of truth is py-service ledger; these are mirror only. Updated by
 * GroupProgressionMirror / OutfitMirror on `gamification.level_up` /
 * `gamification.outfit_unlocked` events.
 *
 * `outfits_owned` defaults to JSON array containing `["none"]` so existing
 * customer rows render the default avatar without further migration.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->unsignedInteger('group_level')->default(1)->after('membership_level');
            $table->unsignedInteger('group_level_xp')->default(0)->after('group_level');
            $table->string('group_level_name_zh', 32)->nullable()->after('group_level_xp');
            $table->string('group_level_name_en', 32)->nullable()->after('group_level_name_zh');
            $table->json('outfits_owned')->nullable()->after('group_level_name_en');
            $table->timestamp('group_level_updated_at')->nullable()->after('outfits_owned');
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn([
                'group_level',
                'group_level_xp',
                'group_level_name_zh',
                'group_level_name_en',
                'outfits_owned',
                'group_level_updated_at',
            ]);
        });
    }
};
