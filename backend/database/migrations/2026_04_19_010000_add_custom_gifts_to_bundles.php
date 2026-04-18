<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 自訂加贈（非商品）— 像營養師課程這種服務型贈品，不在 products 表、
 * 沒有 SKU，只為單次活動自訂，純展示用（前台加贈區 + 訂單備註），不
 * 走庫存也不進 order_items。結構：[{"name":"...","quantity":1}, ...]。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bundles', function (Blueprint $table) {
            $table->json('custom_gifts')->nullable()->after('value_price');
        });
    }

    public function down(): void
    {
        Schema::table('bundles', function (Blueprint $table) {
            $table->dropColumn('custom_gifts');
        });
    }
};
