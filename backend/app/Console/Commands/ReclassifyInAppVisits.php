<?php

namespace App\Console\Commands;

use App\Models\Visit;
use Illuminate\Console\Command;

/**
 * One-shot cleanup: earlier bot regex included `Line/` which falsely
 * flagged LINE in-app browser users (real humans) as crawlers. Move
 * them from the `bot` bucket to `line` / `facebook` / `instagram` based
 * on their UA.
 *
 * Idempotent: safe to re-run.
 */
class ReclassifyInAppVisits extends Command
{
    protected $signature = 'visits:reclassify-inapp {--dry : Preview without writing}';
    protected $description = 'Reclassify historical bot-tagged visits that are actually social-app in-app browsers';

    public function handle(): int
    {
        $rules = [
            'line'      => '~\bLine/~',
            'instagram' => '~\bInstagram\b~',
            'facebook'  => '~FBAN/|FBAV/|FBIOS\b~',
        ];

        $isDry = (bool) $this->option('dry');
        $totalMoved = 0;

        foreach ($rules as $bucket => $pattern) {
            $query = Visit::where('referer_source', 'bot')
                ->where('user_agent', 'like', match ($bucket) {
                    'line'      => '%Line/%',
                    'instagram' => '%Instagram%',
                    'facebook'  => '%FB%',
                });

            $count = 0;
            $query->chunkById(500, function ($visits) use ($pattern, $bucket, $isDry, &$count) {
                foreach ($visits as $v) {
                    if (! preg_match($pattern, (string) $v->user_agent)) continue;
                    if (! $isDry) {
                        $v->referer_source = $bucket;
                        $v->saveQuietly();
                    }
                    $count++;
                }
            });

            $this->line(sprintf('  %-10s  → %s  %d rows', $bucket, $isDry ? '(dry)' : '(updated)', $count));
            $totalMoved += $count;
        }

        $this->info(sprintf('Done. Total reclassified: %d%s', $totalMoved, $isDry ? ' (dry-run)' : ''));
        return self::SUCCESS;
    }
}
