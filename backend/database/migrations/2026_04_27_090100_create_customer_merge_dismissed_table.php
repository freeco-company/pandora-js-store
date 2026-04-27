<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * customer_merge_dismissed — 後台人工標記「這兩個是不同人」，下次掃描跳過。
 *
 * 用 (smaller_id, larger_id) 為 unique key，無論 dedupe 從哪個方向偵測都能命中。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_merge_dismissed', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('customer_a_id'); // 較小的 id
            $table->unsignedBigInteger('customer_b_id'); // 較大的 id
            $table->string('reason', 255)->nullable();   // 後台 admin 留的備註
            $table->unsignedBigInteger('actor_admin_id')->nullable();
            $table->timestamps();

            $table->unique(['customer_a_id', 'customer_b_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_merge_dismissed');
    }
};
