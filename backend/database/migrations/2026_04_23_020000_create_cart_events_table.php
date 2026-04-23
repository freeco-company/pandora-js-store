<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Backend cart-event log. Mirrors a subset of the GA4 / GTM events we're
 * already firing from CartProvider (view_item, add_to_cart, begin_checkout),
 * but persisted so we can join against `visits.session_id` for funnel
 * computation without depending on the GA4 Reporting API.
 *
 * One row per event. Aggregation happens at query time — cheap enough on
 * small volumes and avoids an upsert hot path in the write endpoint.
 *
 * Not meant as a complete analytics store; GA4/GTM remains the source of
 * truth for downstream integrations (Meta CAPI, Google Ads conversions).
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('cart_events', function (Blueprint $table) {
            $table->id();

            // `session_id` joins back to `visits.session_id`. Not FK'd because
            // visits rows can be GC'd and we don't want cart events to error
            // out on an orphan session lookup.
            $table->string('session_id', 64)->nullable()->index();

            // Logged-in customer if we have one. Most cart events will be
            // guest sessions that only get a customer_id at checkout time.
            $table->foreignId('customer_id')->nullable()
                ->constrained('customers')->nullOnDelete();

            // Event type. Keep the enum small and stable — downstream queries
            // filter by this. Matches GA4's recommended names where possible.
            $table->enum('event_type', [
                'view_item',
                'add_to_cart',
                'remove_from_cart',
                'begin_checkout',
                'purchase',
            ])->index();

            // Item — product_id nullable so we can log bundle events too
            // (bundle_id distinguishes). One of the two should be set.
            $table->foreignId('product_id')->nullable()
                ->constrained('products')->nullOnDelete();
            $table->foreignId('bundle_id')->nullable()
                ->constrained('bundles')->nullOnDelete();

            $table->unsignedInteger('quantity')->default(1);

            // Value at time of event (for GA4 "value" convention) — useful
            // later for ROAS/AOV analysis without having to re-resolve prices.
            $table->decimal('value', 10, 2)->nullable();

            $table->timestamp('occurred_at')->index();
            $table->timestamps();

            // Compound indexes matching the pipeline-report queries:
            //  1. "unique sessions that added to cart yesterday"
            //     → WHERE event_type=add_to_cart AND occurred_at BETWEEN ...
            //  2. "funnel rate per day"
            //     → DATE(occurred_at) + event_type grouped
            $table->index(['event_type', 'occurred_at']);
            $table->index(['session_id', 'event_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cart_events');
    }
};
