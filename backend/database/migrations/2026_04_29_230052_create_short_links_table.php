<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('short_links', function (Blueprint $table) {
            $table->id();
            // Short code in the URL: /p/{code}. Custom (e.g. "mday-ig-story") or
            // auto-generated 6-char base36. Length 3-40, [a-z0-9-] only.
            $table->string('code', 40)->unique();
            // Full long URL with utm_* — what the user is redirected to.
            $table->string('target_url', 500);
            // Admin-facing label so /admin/short-links is scannable.
            $table->string('label', 120);
            // Bundle this short link promotes (nullable: future-proof for
            // product / campaign / homepage shortlinks without a bundle).
            $table->foreignId('bundle_id')->nullable()
                ->constrained('bundles')->nullOnDelete();
            // Mirrors utm_campaign so /admin can group short-links by campaign
            // without parsing target_url.
            $table->string('campaign', 128)->nullable();
            $table->unsignedBigInteger('click_count')->default(0);
            // Admin who created the link. Nullable so seeded / system-created
            // links don't break if the admin row is removed.
            $table->foreignId('created_by')->nullable()
                ->constrained('users')->nullOnDelete();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index('bundle_id', 'short_links_bundle_idx');
            $table->index('campaign', 'short_links_campaign_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('short_links');
    }
};
