<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Flag visits / cart_events from team-member testing so admin analytics
 * (StatsOverview, VisitTrendChart, PaidLandingWasteWidget, daily report)
 * stop being skewed by our own activity.
 *
 * The flag is set at write time by VisitController / CartEventController
 * based on config('analytics.internal_emails') and 'internal_ips'.
 *
 * After this migration runs, an artisan command `analytics:backfill-internal`
 * (added separately) re-flags historical rows. We keep the rows themselves
 * (rather than deleting) so we still have an audit trail of debug sessions.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('visits', function (Blueprint $table) {
            $table->boolean('is_internal')->default(false)->after('country')->index();
        });

        Schema::table('cart_events', function (Blueprint $table) {
            $table->boolean('is_internal')->default(false)->after('customer_id')->index();
        });

        // Backfill: any visit whose IP matches the configured internal list,
        // or whose customer_id resolves to an internal email, gets flagged.
        // Cart events: any session_id that ever appeared in an internal visit
        // PLUS any customer_id matching internal email.
        $emails = (array) config('analytics.internal_emails', []);
        $ips = (array) config('analytics.internal_ips', []);

        $internalCustomerIds = ! empty($emails)
            ? DB::table('customers')->whereIn('email', $emails)->pluck('id')->all()
            : [];

        if (! empty($ips) || ! empty($internalCustomerIds)) {
            DB::table('visits')
                ->where(function ($q) use ($ips, $internalCustomerIds) {
                    if (! empty($ips)) $q->whereIn('ip', $ips);
                    if (! empty($internalCustomerIds)) $q->orWhereIn('customer_id', $internalCustomerIds);
                })
                ->update(['is_internal' => true]);

            $internalSessionIds = DB::table('visits')
                ->where('is_internal', true)
                ->whereNotNull('session_id')
                ->pluck('session_id')
                ->unique()
                ->values()
                ->all();

            DB::table('cart_events')
                ->where(function ($q) use ($internalSessionIds, $internalCustomerIds) {
                    if (! empty($internalSessionIds)) $q->whereIn('session_id', $internalSessionIds);
                    if (! empty($internalCustomerIds)) $q->orWhereIn('customer_id', $internalCustomerIds);
                })
                ->update(['is_internal' => true]);
        }
    }

    public function down(): void
    {
        Schema::table('visits', function (Blueprint $table) {
            $table->dropIndex(['is_internal']);
            $table->dropColumn('is_internal');
        });

        Schema::table('cart_events', function (Blueprint $table) {
            $table->dropIndex(['is_internal']);
            $table->dropColumn('is_internal');
        });
    }
};
