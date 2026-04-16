<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('articles', function (Blueprint $table) {
            $table->boolean('is_pinned')->default(false)->after('status');
            $table->integer('sort_order')->default(0)->after('is_pinned');
        });
    }

    public function down(): void
    {
        Schema::table('articles', function (Blueprint $table) {
            $table->dropColumn(['is_pinned', 'sort_order']);
        });
    }
};
