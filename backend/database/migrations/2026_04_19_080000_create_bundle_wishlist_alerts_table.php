<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // De-dupe ledger for "your wishlist item is in a limited bundle"
        // alerts. One row per (bundle, customer) pair guarantees we never
        // nudge the same customer about the same bundle twice — even if
        // the cron runs multiple times within the 24h window.
        Schema::create('bundle_wishlist_alerts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bundle_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->timestamp('sent_at')->useCurrent();

            $table->unique(['bundle_id', 'customer_id']);
            $table->index('sent_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bundle_wishlist_alerts');
    }
};
