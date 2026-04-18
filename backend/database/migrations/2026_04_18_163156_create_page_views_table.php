<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lightweight page-view log for the dashboard "今日瀏覽人數" widget.
 *
 * One row per tracked hit. Unique visitor = COUNT(DISTINCT session_id)
 * over a day. session_id is a client-generated UUID persisted in
 * localStorage, so returning visitors on the same device count once
 * per session.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('page_views', function (Blueprint $table) {
            $table->id();
            $table->string('path', 255)->index();
            $table->char('session_id', 36)->index();
            $table->string('ip', 45)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->string('referer', 500)->nullable();
            $table->timestamp('created_at')->useCurrent()->index();

            $table->index(['created_at', 'session_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('page_views');
    }
};
