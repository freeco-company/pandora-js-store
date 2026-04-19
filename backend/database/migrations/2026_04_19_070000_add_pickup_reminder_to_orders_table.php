<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // When the parcel arrived at the CVS store (RtnCode 2030 callback).
            // Used as the anchor for "5 days since arrival" pickup-reminder logic.
            $table->timestamp('shipped_at')->nullable()->after('logistics_created_at');
            // Tracks whether we already nudged the customer to pick up — keeps
            // the reminder cron idempotent (one nudge per parcel).
            $table->timestamp('pickup_reminder_sent_at')->nullable()->after('shipped_at');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['shipped_at', 'pickup_reminder_sent_at']);
        });
    }
};
