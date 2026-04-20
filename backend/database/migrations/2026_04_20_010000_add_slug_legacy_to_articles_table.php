<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds slug_legacy so we can repair corrupted hex-byte article slugs
 * (319 rows from the original WP scraper's fallback path) without losing
 * existing inbound traffic. Same pattern products already use — lookup
 * by slug OR slug_legacy, with slug_legacy serving a 301 to the new slug.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('articles', function (Blueprint $table) {
            $table->string('slug_legacy', 255)->nullable()->after('slug')->index();
        });
    }

    public function down(): void
    {
        Schema::table('articles', function (Blueprint $table) {
            $table->dropIndex(['slug_legacy']);
            $table->dropColumn('slug_legacy');
        });
    }
};
