<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // Traffic attribution at time of checkout — lets /admin/orders
            // answer "how did this order come in?" (IG post vs LINE msg vs
            // Meta Ads vs direct). Captured from a client-side cookie that
            // stores the landing-page utm_* + click_id for 30 days.
            $table->string('referer_source', 32)->nullable()->after('booking_note');
            $table->string('utm_source', 64)->nullable()->after('referer_source');
            $table->string('utm_medium', 64)->nullable()->after('utm_source');
            $table->string('utm_campaign', 128)->nullable()->after('utm_medium');
            $table->string('landing_path', 255)->nullable()->after('utm_campaign');

            $table->index('referer_source', 'orders_referer_source_idx');
            $table->index('utm_campaign', 'orders_utm_campaign_idx');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex('orders_referer_source_idx');
            $table->dropIndex('orders_utm_campaign_idx');
            $table->dropColumn([
                'referer_source', 'utm_source', 'utm_medium',
                'utm_campaign', 'landing_path',
            ]);
        });
    }
};
