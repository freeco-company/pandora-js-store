<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Preserve the WP-imported slug so old URLs can 301-redirect
 * to the cleaned Chinese slug.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('slug_legacy', 500)->nullable()->index()->after('slug');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex(['slug_legacy']);
            $table->dropColumn('slug_legacy');
        });
    }
};
