<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Add `cod_no_pickup` value to the orders.status enum.
 * Used when a COD pickup times out at CVS or is refused at doorstep —
 * triggers automatic blacklisting via OrderObserver.
 */
return new class extends Migration
{
    public function up(): void
    {
        // MySQL/MariaDB only — SQLite (used in tests) doesn't enforce enum so a no-op is fine.
        $driver = DB::connection()->getDriverName();
        if ($driver === 'mysql' || $driver === 'mariadb') {
            DB::statement(
                "ALTER TABLE orders MODIFY status ENUM("
                    ."'pending','processing','shipped','completed','cancelled','refunded','cod_no_pickup'"
                .") NOT NULL DEFAULT 'pending'"
            );
        }
    }

    public function down(): void
    {
        $driver = DB::connection()->getDriverName();
        if ($driver === 'mysql' || $driver === 'mariadb') {
            DB::statement(
                "ALTER TABLE orders MODIFY status ENUM("
                    ."'pending','processing','shipped','completed','cancelled','refunded'"
                .") NOT NULL DEFAULT 'pending'"
            );
        }
    }
};
