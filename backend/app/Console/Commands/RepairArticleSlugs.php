<?php

namespace App\Console\Commands;

use App\Models\Article;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Overtrue\Pinyin\Pinyin;

/**
 * Repair the 319 articles whose slugs are raw UTF-8 hex bytes
 * (WP scraper fallback: bloge7879fe9a48a…). Generates a pinyin-based
 * slug from the title and preserves the old slug in slug_legacy for
 * 301 redirects.
 *
 * Run:  php artisan articles:repair-slugs --dry
 *       php artisan articles:repair-slugs
 *
 * Idempotent — already-repaired articles (proper ASCII slug) are skipped.
 */
class RepairArticleSlugs extends Command
{
    protected $signature = 'articles:repair-slugs {--dry : Show before/after without saving}';

    protected $description = 'Regenerate hex-corrupted article slugs to pinyin. Preserves old in slug_legacy for 301.';

    /** Hex run of ≥16 chars = the WP scraper bin2hex fallback signature. */
    private const HEX_PATTERN = '/[0-9a-f]{16,}/';

    public function handle(): int
    {
        $dry = (bool) $this->option('dry');

        $articles = Article::where('slug', 'REGEXP', '[0-9a-f]{16,}')->get();
        $this->info("Found {$articles->count()} articles with hex-corrupted slugs.");

        if ($articles->isEmpty()) {
            return self::SUCCESS;
        }

        $pinyin = new Pinyin();
        $rows = [];
        $changed = 0;
        $taken = Article::whereNotIn('id', $articles->pluck('id'))->pluck('slug')->flip();

        foreach ($articles as $a) {
            $base = $this->buildSlug($pinyin, $a->title, $a->source_type);
            $new = $this->uniquify($base, $taken);
            $taken[$new] = true;

            $rows[] = [
                $a->id,
                mb_strimwidth($a->slug, 0, 40, '…'),
                mb_strimwidth($new, 0, 60, '…'),
            ];

            if (! $dry) {
                if (empty($a->slug_legacy)) {
                    $a->slug_legacy = $a->slug;
                }
                $a->slug = $new;
                $a->saveQuietly();
                $changed++;
            }
        }

        $this->table(['ID', 'Old (hex)', 'New (pinyin)'], array_slice($rows, 0, 30));
        if (count($rows) > 30) $this->line('  …and ' . (count($rows) - 30) . ' more');

        $this->info($dry
            ? "Dry-run: would repair " . count($rows) . " slugs."
            : "Repaired {$changed} slugs. Regenerate sitemap + clear article cache next.");

        return self::SUCCESS;
    }

    /**
     * Build a slug: `{source_type}-{pinyin-of-title}`.
     * Pinyin without tones, lowercased, hyphen-separated. Empty-pinyin
     * fallback is `article-{id}` — upstream caller handles that via uniquify.
     */
    private function buildSlug(Pinyin $pinyin, string $title, ?string $sourceType): string
    {
        $prefix = $sourceType ?: 'article';

        // Strip HTML entities + punctuation so pinyin only sees real characters
        $clean = html_entity_decode(strip_tags($title), ENT_QUOTES, 'UTF-8');
        $clean = preg_replace('/[\p{P}\p{S}]+/u', ' ', $clean);

        $py = $pinyin->permalink($clean, '-'); // e.g. "ying-yang-shi-jiao-ni-..."

        // Keep it short enough for URLs. 80 chars of pinyin ≈ 12-14 Chinese chars worth.
        $py = Str::limit($py, 80, '');
        $py = trim($py, '-');

        return $py !== '' ? "{$prefix}-{$py}" : $prefix;
    }

    /** Resolve collisions with a numeric suffix. */
    private function uniquify(string $base, \Illuminate\Support\Collection|array $taken): string
    {
        $check = fn ($s) => $taken instanceof \Illuminate\Support\Collection
            ? $taken->has($s)
            : isset($taken[$s]);

        if (! $check($base)) return $base;

        $i = 2;
        while ($check("{$base}-{$i}")) $i++;
        return "{$base}-{$i}";
    }
}
