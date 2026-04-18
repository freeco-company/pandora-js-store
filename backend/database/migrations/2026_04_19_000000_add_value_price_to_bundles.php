<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Manual 「價值」 field on bundles — the strikethrough / anchor price shown
 * alongside 套組價. Previously we derived it from sum(buy items' retail × qty),
 * but admins want to overstate (include gift value) or round for marketing,
 * so this is a simple optional override. Falls back to the computed figure
 * when null — see Bundle::valuePrice().
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bundles', function (Blueprint $table) {
            $table->decimal('value_price', 10, 2)->nullable()->after('image');
        });
    }

    public function down(): void
    {
        Schema::table('bundles', function (Blueprint $table) {
            $table->dropColumn('value_price');
        });
    }
};
