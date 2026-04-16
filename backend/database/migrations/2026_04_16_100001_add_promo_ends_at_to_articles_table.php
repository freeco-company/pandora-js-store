<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('articles', function (Blueprint $table) {
            // Populated by ScrapeJerosseArticles when the body contains a
            // 「▲活動時間：YYYY/MM/DD … - YYYY/MM/DD HH:MM」 pattern.
            // Index so we can efficiently filter expired promos at query time.
            $table->timestamp('promo_ends_at')->nullable()->after('is_pinned')->index();
        });
    }

    public function down(): void
    {
        Schema::table('articles', function (Blueprint $table) {
            $table->dropIndex(['promo_ends_at']);
            $table->dropColumn('promo_ends_at');
        });
    }
};
