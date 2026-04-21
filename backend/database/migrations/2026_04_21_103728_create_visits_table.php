<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('visits', function (Blueprint $table) {
            $table->id();
            // Deterministic dedup id = hash(ip_prefix + ua + day). Same visitor
            // on same day collapses to one logical visitor for UV counting.
            $table->string('visitor_id', 64)->index();
            // Session id (from cookie) — tracks multi-page journeys within a
            // single session. Null for bots / cookie-off browsers.
            $table->string('session_id', 64)->nullable()->index();
            $table->string('ip', 45)->nullable()->index();
            $table->string('country', 2)->nullable()->index();
            $table->string('region', 64)->nullable();

            $table->text('user_agent')->nullable();
            $table->string('device_type', 16)->nullable()->index();
            $table->string('os', 32)->nullable()->index();
            $table->string('os_version', 32)->nullable();
            $table->string('browser', 32)->nullable()->index();
            $table->string('browser_version', 32)->nullable();

            // Normalized source bucket: google / facebook / instagram / line /
            // email / direct / google_ads / bing / other. Used for breakdown
            // aggregations; raw URL kept in referer_url for drill-down.
            $table->string('referer_source', 32)->nullable()->index();
            $table->text('referer_url')->nullable();

            // Campaign attribution (UTM)
            $table->string('utm_source', 64)->nullable()->index();
            $table->string('utm_medium', 64)->nullable();
            $table->string('utm_campaign', 128)->nullable()->index();

            $table->string('landing_path', 512)->nullable();
            $table->string('path', 512)->nullable();

            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();

            $table->timestamp('visited_at')->index();
            $table->timestamps();

            // Hot queries:
            //   1. today's unique visitors → visited_at + visitor_id
            //   2. source breakdown        → visited_at + referer_source
            $table->index(['visited_at', 'visitor_id']);
            $table->index(['visited_at', 'referer_source']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('visits');
    }
};
