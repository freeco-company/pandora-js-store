<?php

namespace App\Console\Commands;

use App\Models\Customer;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * ADR-007 §6 risk #4 mitigation (b) consumer.
 *
 * Periodically pulls Pandora Core's `/api/internal/reconcile/users?since=<cursor>`
 * and updates the local `customers` mirror. Used as a safety net when the
 * identity webhook chain drops events.
 *
 * Cursor stored in Cache under `identity:reconcile:cursor`. The endpoint is
 * `since`-inclusive so resuming from a saved cursor is idempotent (upsert).
 *
 * NOT for first-time backfill — that's `customer:backfill-identities` /
 * `identity:backfill-to-platform`. This is incremental delta only.
 *
 * Schedule: hourly (matches ADR-007 §6 #4(a) consumer mirror TTL).
 */
class IdentityReconcileCmd extends Command
{
    protected $signature = 'identity:reconcile
        {--since= : ISO-8601 cursor override (testing / one-shot)}
        {--limit=100 : Page size (capped server-side at 500)}
        {--dry-run : Fetch + log without writing local mirror}
        {--max-pages=50 : Safety cap on pagination loop}';

    protected $description = 'Pull identity delta from Pandora Core (ADR-007 reconcile)';

    private const CURSOR_KEY = 'identity:reconcile:cursor';

    public function handle(): int
    {
        $base = rtrim((string) config('services.pandora_core.base_url'), '/');
        $secret = (string) config('services.pandora_core.internal_secret');
        if ($base === '' || $secret === '') {
            $this->error('PANDORA_CORE_BASE_URL / PANDORA_CORE_INTERNAL_SECRET not configured');

            return self::FAILURE;
        }

        $sinceOpt = $this->option('since');
        $cursor = is_string($sinceOpt) && $sinceOpt !== ''
            ? $sinceOpt
            : (string) Cache::get(self::CURSOR_KEY, '1970-01-01T00:00:00Z');
        $limit = max(1, min(500, (int) $this->option('limit')));
        $maxPages = max(1, (int) $this->option('max-pages'));
        $dryRun = (bool) $this->option('dry-run');

        $this->line(sprintf('reconcile from cursor=%s limit=%d max-pages=%d dry-run=%s',
            $cursor, $limit, $maxPages, $dryRun ? 'yes' : 'no'));

        $totalUpserted = 0;
        $totalSeen = 0;
        $page = 0;

        while ($page < $maxPages) {
            $page++;
            $resp = Http::withHeaders([
                'X-Pandora-Internal-Secret' => $secret,
                'Accept' => 'application/json',
            ])
                ->timeout(5)
                ->retry(2, 200, throw: false)
                ->get($base.'/api/internal/reconcile/users', [
                    'since' => $cursor,
                    'limit' => $limit,
                ]);

            if (! $resp->successful()) {
                $this->error(sprintf('page %d failed: status=%d body=%s',
                    $page, $resp->status(), substr((string) $resp->body(), 0, 200)));

                return self::FAILURE;
            }

            /** @var array{users?: array<int, array<string, mixed>>, next_cursor?: ?string, has_more?: bool, count?: int} $body */
            $body = (array) $resp->json();
            $users = (array) ($body['users'] ?? []);
            $nextCursor = $body['next_cursor'] ?? null;
            $hasMore = (bool) ($body['has_more'] ?? false);

            foreach ($users as $u) {
                if (! is_array($u) || empty($u['id'])) {
                    continue;
                }
                $totalSeen++;
                if (! $dryRun) {
                    if ($this->upsertMirror($u)) {
                        $totalUpserted++;
                    }
                }
            }

            $this->line(sprintf('  page %d: count=%d has_more=%s',
                $page, count($users), $hasMore ? 'yes' : 'no'));

            if (! $hasMore || ! is_string($nextCursor)) {
                break;
            }
            $cursor = $nextCursor;
        }

        if (! $dryRun && $page > 0) {
            Cache::forever(self::CURSOR_KEY, Carbon::now('UTC')->toIso8601String());
        }

        $this->info(sprintf('done. pages=%d seen=%d upserted=%d',
            $page, $totalSeen, $totalUpserted));

        if ($totalSeen > 0 && ! $dryRun) {
            Log::info('[identity:reconcile] synced', [
                'pages' => $page,
                'seen' => $totalSeen,
                'upserted' => $totalUpserted,
            ]);
        }

        return self::SUCCESS;
    }

    /**
     * Upsert one user from the reconcile response into customers.
     * Returns true if a row changed.
     *
     * Note: 母艦 customer is NOT auto-created from reconcile — only existing
     * customers with a matching pandora_user_uuid get their display fields
     * refreshed. New users come in via OAuth / Pandora Core webhook /
     * BackfillCustomerIdentitiesCmd, not here.
     *
     * @param  array<string, mixed>  $u
     */
    private function upsertMirror(array $u): bool
    {
        $uuid = (string) $u['id'];
        $row = Customer::where('pandora_user_uuid', $uuid)->first();
        if ($row === null) {
            return false;
        }

        $changed = false;
        $displayName = isset($u['display_name']) && is_string($u['display_name'])
            ? $u['display_name']
            : null;
        if ($displayName !== null && $displayName !== '' && $row->name !== $displayName) {
            $row->name = $displayName;
            $changed = true;
        }
        if ($changed) {
            $row->save();
        }

        return $changed;
    }
}
