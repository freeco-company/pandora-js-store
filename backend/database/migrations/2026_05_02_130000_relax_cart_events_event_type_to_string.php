<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * v2.12.0 added 4 new event_type values (checkout_form_filled,
 * checkout_payment_selected, checkout_submit_attempt, checkout_submit_failed)
 * but the original migration had `enum(...)` on the column. On MariaDB an
 * ENUM rejects unknown values silently (insert fails), and the frontend's
 * fire-and-forget POST swallows the error → none of the new sub-step
 * events were actually being stored on prod.
 *
 * Fix: relax the column to varchar(40). The application layer
 * (CartEventController::EVENT_TYPES) is the single source of truth for
 * which event_types are valid; pinning the DB enum just rotted out of
 * sync with no security benefit.
 */
return new class extends Migration
{
    public function up(): void
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'mysql' || $driver === 'mariadb') {
            // Direct ALTER preserves the existing data and the index on event_type.
            DB::statement("ALTER TABLE cart_events MODIFY COLUMN event_type VARCHAR(40) NOT NULL");
        } else {
            // SQLite (test DB) — recreate the column to drop the CHECK
            // constraint. RefreshDatabase rebuilds from scratch so
            // production data isn't a concern here. All compound indexes
            // referencing the column must be dropped first or SQLite
            // complains about dangling references.
            Schema::table('cart_events', function (Blueprint $t) {
                $t->dropIndex(['event_type']);
                $t->dropIndex(['event_type', 'occurred_at']);
                $t->dropIndex(['session_id', 'event_type']);
                $t->dropColumn('event_type');
            });
            Schema::table('cart_events', function (Blueprint $t) {
                $t->string('event_type', 40)->after('customer_id')->index();
                $t->index(['event_type', 'occurred_at']);
                $t->index(['session_id', 'event_type']);
            });
        }
    }

    public function down(): void
    {
        // Intentionally no-down — restoring the enum would require knowing
        // the exact set of values in use at restore time, and the down
        // migration is for development-only rollback. If you really need
        // to revert, edit the original create migration instead.
    }
};
