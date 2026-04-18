<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Bundle promotion model: each pivot row says whether the product is a
 * "buy" item (counts towards price) or a "gift" item (free), plus a
 * quantity per-role. The unique index moves from (campaign, product)
 * to (campaign, product, role) because the same SKU can legitimately
 * be both bought and gifted in the same bundle (e.g. buy 3 probiotic,
 * get 1 probiotic free).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('campaign_product', function (Blueprint $table) {
            $table->string('role', 8)->default('buy')->after('product_id'); // 'buy' | 'gift'
            $table->unsignedInteger('quantity')->default(1)->after('role');
        });

        // Add the new unique BEFORE dropping the old one — the old index is
        // referenced by the campaign_id foreign key, which would otherwise
        // refuse the drop. The new index covers (campaign_id, product_id, ...)
        // as a prefix so the FK stays satisfied.
        Schema::table('campaign_product', function (Blueprint $table) {
            $table->unique(['campaign_id', 'product_id', 'role']);
        });
        Schema::table('campaign_product', function (Blueprint $table) {
            $table->dropUnique(['campaign_id', 'product_id']);
        });

        // Pre-existing rows default to buy/qty=1 so nothing breaks.
        DB::table('campaign_product')->whereNull('role')->update(['role' => 'buy', 'quantity' => 1]);
    }

    public function down(): void
    {
        Schema::table('campaign_product', function (Blueprint $table) {
            $table->unique(['campaign_id', 'product_id']);
        });
        Schema::table('campaign_product', function (Blueprint $table) {
            $table->dropUnique(['campaign_id', 'product_id', 'role']);
        });
        Schema::table('campaign_product', function (Blueprint $table) {
            $table->dropColumn(['role', 'quantity']);
        });
    }
};
