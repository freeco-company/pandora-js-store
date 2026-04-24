<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Repair imported WP/WooCommerce orders' created_at column.
 *
 * ImportWpData set every imported order's created_at to the import moment
 * (2026-04-18 afternoon) — useless for date-based reporting. This command
 * reads the original WP SQL dump, extracts each order's date_created_gmt
 * from wp_wc_orders, and writes the correct UTC → Asia/Taipei timestamp
 * back onto the local orders row matched by wp_order_id.
 *
 * Safety:
 *   - --dry: print the plan, don't write.
 *   - Non-WP orders (wp_order_id IS NULL) are never touched.
 *   - Run the provided mysqldump backup command before executing for real.
 *
 * Usage:
 *   php artisan orders:repair-wp-dates /root/awoo_wp_2026-04-12.sql --dry
 *   php artisan orders:repair-wp-dates /root/awoo_wp_2026-04-12.sql
 */
class RepairWpOrderDates extends Command
{
    protected $signature = 'orders:repair-wp-dates {sql_path} {--dry}';
    protected $description = 'Backfill created_at on WP-imported orders from the original wp_wc_orders.date_created_gmt';

    public function handle(): int
    {
        $path = $this->argument('sql_path');
        if (! is_file($path)) {
            $this->error("SQL file not found: {$path}");
            return self::FAILURE;
        }

        $this->info('Parsing wp_wc_orders INSERT from dump…');
        $map = $this->extractOrderDates($path);
        $this->info(sprintf('Found %d order rows with date_created_gmt', count($map)));

        if (empty($map)) {
            $this->warn('Nothing to do.');
            return self::SUCCESS;
        }

        $dry = (bool) $this->option('dry');
        $updated = 0;
        $missing = 0;
        $unchanged = 0;
        $tz = 'Asia/Taipei';

        foreach ($map as $wpId => $dateGmt) {
            $order = DB::table('orders')
                ->where('wp_order_id', $wpId)
                ->first(['id', 'order_number', 'created_at']);

            if (! $order) {
                $missing++;
                continue;
            }

            try {
                $newDate = Carbon::parse($dateGmt, 'UTC')->setTimezone($tz);
            } catch (\Throwable $e) {
                $this->warn("Skip wp_id={$wpId}: bad date '{$dateGmt}'");
                continue;
            }

            $existingStr = (string) $order->created_at;
            $newStr = $newDate->format('Y-m-d H:i:s');
            if ($existingStr === $newStr) {
                $unchanged++;
                continue;
            }

            if (! $dry) {
                DB::table('orders')
                    ->where('id', $order->id)
                    ->update([
                        'created_at' => $newStr,
                        'updated_at' => $newStr,
                    ]);
            }
            $updated++;
            if ($updated <= 5 || $this->getOutput()->isVerbose()) {
                $this->line(sprintf('  %s  %s  →  %s', $order->order_number, $existingStr, $newStr));
            }
        }

        $this->newLine();
        $this->info(sprintf(
            '%s: updated=%d, unchanged=%d, missing=%d',
            $dry ? 'Dry-run' : 'Done',
            $updated,
            $unchanged,
            $missing,
        ));

        if ($dry) {
            $this->comment('Re-run without --dry to commit. Backup first:');
            $this->line('  mysqldump -u root -p pandora_shop orders > /var/backups/pandora/orders-pre-wp-date-fix-$(date +%Y%m%d-%H%M%S).sql');
        }

        return self::SUCCESS;
    }

    /**
     * Extract wp_wc_orders rows from the dump, returning a
     * [wp_id => 'YYYY-MM-DD HH:MM:SS'] map keyed by order id.
     *
     * We only care about the id + date_created_gmt columns, but SQL VALUES
     * must be parsed positionally so we still walk the whole row.
     *
     * @return array<int,string>
     */
    private function extractOrderDates(string $path): array
    {
        $buffer = $this->readWcOrdersInsert($path);
        if ($buffer === null) {
            throw new \RuntimeException('wp_wc_orders INSERT not found in dump');
        }

        $pos = stripos($buffer, 'VALUES');
        if ($pos === false) {
            throw new \RuntimeException('wp_wc_orders INSERT has no VALUES');
        }
        $values = rtrim(substr($buffer, $pos + 6), ";\n ");

        // Column order from the dump (confirmed 2026-04-12 export):
        //   0 id, 1 status, 2 currency, 3 type, 4 tax_amount, 5 total_amount,
        //   6 customer_id, 7 billing_email, 8 date_created_gmt, 9 date_updated_gmt,
        //   10 parent_order_id, 11 payment_method, …
        // Only shop_order rows are real orders; refunds & sub-orders get a
        // different `type` and we skip them.
        $out = [];
        $this->iterateRows($values, function (array $cols) use (&$out) {
            if (count($cols) < 9) return;
            $id = (int) $cols[0];
            $type = trim((string) $cols[3]);
            $date = trim((string) $cols[8]);
            if ($id <= 0 || $type !== 'shop_order') return;
            if ($date === '' || strtoupper($date) === 'NULL') return;
            $out[$id] = $date;
        });

        return $out;
    }

    /** Slurp the single (multi-line) INSERT INTO `wp_wc_orders` statement. */
    private function readWcOrdersInsert(string $path): ?string
    {
        $fh = fopen($path, 'r');
        if (! $fh) throw new \RuntimeException("Cannot open {$path}");

        $inInsert = false;
        $buf = '';
        while (($line = fgets($fh)) !== false) {
            if (! $inInsert) {
                if (preg_match('/^INSERT INTO `wp_wc_orders` \(`id`/', $line)) {
                    $inInsert = true;
                    $buf = $line;
                    if (str_ends_with(rtrim($line), ';')) break;
                }
                continue;
            }
            $buf .= $line;
            if (str_ends_with(rtrim($line), ';')) break;
        }
        fclose($fh);
        return $inInsert ? $buf : null;
    }

    /**
     * Walk each `(...)` row in a multi-row VALUES clause, invoking $cb with
     * the array of unquoted column strings. Handles single-quoted strings
     * with backslash escapes — not perfect for BLOBs but fine for known
     * WooCommerce columns (no binary in wp_wc_orders).
     *
     * @param  callable(array<int,string>):void  $cb
     */
    private function iterateRows(string $input, callable $cb): void
    {
        $i = 0;
        $len = strlen($input);
        while ($i < $len) {
            while ($i < $len && (ctype_space($input[$i]) || $input[$i] === ',')) $i++;
            if ($i >= $len) break;
            if ($input[$i] !== '(') { $i++; continue; }
            $i++; // skip (

            $cols = [];
            $current = '';
            $inStr = false;

            while ($i < $len) {
                $c = $input[$i];
                if ($inStr) {
                    if ($c === '\\' && $i + 1 < $len) {
                        $next = $input[$i + 1];
                        $current .= match ($next) {
                            'n' => "\n", 'r' => "\r", 't' => "\t",
                            '\\' => '\\', "'" => "'", '"' => '"',
                            '0' => "\0",
                            default => $next,
                        };
                        $i += 2;
                        continue;
                    }
                    if ($c === "'") { $inStr = false; $i++; continue; }
                    $current .= $c; $i++;
                } else {
                    if ($c === "'") { $inStr = true; $i++; continue; }
                    if ($c === ',') { $cols[] = $current; $current = ''; $i++; continue; }
                    if ($c === ')') { $cols[] = $current; $i++; $cb($cols); break; }
                    $current .= $c; $i++;
                }
            }
        }
    }
}
