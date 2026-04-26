<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tracks which articles/products have been LLM-rewritten (vs the original
 * scraped/imported content). NULL = original; non-null = rewrite timestamp.
 *
 * Used to drive batched rewrite passes — the worker picks the next bucket
 * via `WHERE rewritten_at IS NULL ORDER BY ...`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('articles', function (Blueprint $table) {
            $table->timestamp('rewritten_at')->nullable()->after('promo_ends_at');
            $table->index('rewritten_at');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->timestamp('rewritten_at')->nullable()->after('wp_id');
            $table->index('rewritten_at');
        });
    }

    public function down(): void
    {
        Schema::table('articles', function (Blueprint $table) {
            $table->dropIndex(['rewritten_at']);
            $table->dropColumn('rewritten_at');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex(['rewritten_at']);
            $table->dropColumn('rewritten_at');
        });
    }
};
