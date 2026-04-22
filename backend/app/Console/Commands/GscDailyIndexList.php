<?php

namespace App\Console\Commands;

use App\Models\Article;
use App\Models\Product;
use App\Models\ProductCategory;
use Illuminate\Console\Command;

/**
 * Print a prioritized URL list for manual GSC "Request indexing" work.
 *
 * Google Search Console allows ~10 URL submissions per day and Google ignores
 * IndexNow, so the user needs a short, high-signal list each morning.
 *
 * Persistent markdown checklist mode (default):
 *   - Writes/appends to --file (default: ~/Desktop/gsc-indexing.md)
 *   - Today's URLs appear as `- [ ]` checkboxes under a dated section
 *   - User ticks `[x]` in the editor when they submit to GSC
 *   - Tomorrow's run parses the file and skips any URL already ticked
 *     anywhere in the file, so the list shrinks over time naturally
 *
 * Usage:
 *   php artisan gsc:today                      # append to default file
 *   php artisan gsc:today --stdout             # print to terminal instead
 *   php artisan gsc:today --flat               # URLs only, no headers
 *   php artisan gsc:today --limit=20
 *   php artisan gsc:today --file=/tmp/x.md
 */
class GscDailyIndexList extends Command
{
    protected $signature = 'gsc:today
        {--stdout : Print to stdout instead of writing to the checklist file}
        {--flat : When stdout, emit URLs only without headers}
        {--limit=10 : Max total URLs for today}
        {--file= : Override path to the persistent checklist file}';

    protected $description = 'Generate today\'s prioritized URL list for manual GSC indexing as a persistent markdown checklist.';

    public function handle(): int
    {
        $host = (string) config('services.indexnow.host', 'pandora.js-store.com.tw');
        $base = "https://{$host}";
        $limit = (int) $this->option('limit');
        $file = $this->resolveFilePath();

        // Parse existing checklist to know which URLs are already submitted
        // (ticked as [x] anywhere in history). Pending [ ] items are fair
        // game to suppress too — we already suggested them, repeating every
        // day would mean the list is useless after a week.
        [$submitted, $alreadyListed] = $this->parseChecklist($file);

        $sections = $this->buildSections($base, $limit, $submitted, $alreadyListed);
        $sections = $this->trimToLimit($sections, $limit);

        if ($this->option('stdout')) {
            return $this->emitStdout($sections);
        }

        $this->appendToChecklist($file, $sections, $host);

        $this->info("已更新 {$file}");
        $this->line("總計新增：{$this->countUrls($sections)} 個 URL · 今天的清單在檔案最上方");
        $this->line("提示：在編輯器打開 → GSC 貼一條 → 檔案把 [ ] 改成 [x] → 明天不會再出現");

        return self::SUCCESS;
    }

    private function resolveFilePath(): string
    {
        if ($override = $this->option('file')) return $override;
        // Default to the GSC plan doc at the monorepo root (one level above
        // backend/). base_path() returns the Laravel app dir = backend/, so
        // dirname() walks up to the repo root where GSC_URL_SUBMISSION_PLAN.md
        // lives alongside the frontend/ and CLAUDE.md.
        return dirname(base_path()) . '/GSC_URL_SUBMISSION_PLAN.md';
    }

    /**
     * @return array{0: array<string,true>, 1: array<string,true>}
     *   [submitted (ticked) URLs, all previously listed URLs]
     */
    private function parseChecklist(string $file): array
    {
        $submitted = [];
        $listed = [];
        if (! is_file($file)) return [$submitted, $listed];

        $lines = file($file, FILE_IGNORE_NEW_LINES);
        foreach ($lines as $line) {
            // Match URL anywhere after the checkbox; trailing text (comments,
            // timestamps, notes) is ignored so users can annotate freely.
            if (preg_match('/^\s*-\s+\[([ xX])\]\s+<?(https?:\/\/[^\s<>]+)/', $line, $m)) {
                $url = rtrim($m[2], '.,);'); // strip punctuation that commonly trails URLs
                $listed[$url] = true;
                if (strtolower($m[1]) === 'x') $submitted[$url] = true;
            }
        }
        return [$submitted, $listed];
    }

    /**
     * @param array<string,true> $submitted
     * @param array<string,true> $alreadyListed
     * @return list<array{0:string, 1:list<string>}>
     */
    private function buildSections(string $base, int $limit, array $submitted, array $alreadyListed): array
    {
        $skip = $submitted + $alreadyListed; // union — skip both "done" and "pending"
        $filter = fn (array $urls): array => array_values(array_filter($urls, fn ($u) => ! isset($skip[$u])));

        $sections = [];

        $home = $filter([$base . '/']);
        if ($home) $sections[] = ['Tier 1 · 首頁', $home];

        $newProducts = Product::where('is_active', true)
            ->where('created_at', '>=', now()->subDays(7))
            ->orderByDesc('created_at')
            ->limit(5)
            ->pluck('slug')
            ->map(fn ($s) => "{$base}/products/{$s}")
            ->all();
        $newProducts = $filter($newProducts);
        if ($newProducts) $sections[] = ['Tier 1 · 新上架商品（近 7 天）', array_slice($newProducts, 0, 3)];

        $newArticles = Article::where('status', 'published')
            ->where('published_at', '>=', now()->subDays(7))
            ->orderByDesc('published_at')
            ->limit(5)
            ->pluck('slug')
            ->map(fn ($s) => "{$base}/articles/{$s}")
            ->all();
        $newArticles = $filter($newArticles);
        if ($newArticles) $sections[] = ['Tier 2 · 新發布文章（近 7 天）', array_slice($newArticles, 0, 3)];

        $topCategories = ProductCategory::select('product_categories.slug')
            ->join('product_product_category as pivot', 'pivot.product_category_id', '=', 'product_categories.id')
            ->join('products', 'products.id', '=', 'pivot.product_id')
            ->where('products.is_active', true)
            ->where('product_categories.slug', '!=', 'uncategorized')
            ->groupBy('product_categories.id', 'product_categories.slug')
            ->orderByRaw('COUNT(products.id) DESC')
            ->limit(5)
            ->pluck('slug')
            ->map(fn ($s) => "{$base}/products/category/{$s}")
            ->all();
        $topCategories = $filter($topCategories);
        if ($topCategories) $sections[] = ['Tier 3 · 熱門分類', array_slice($topCategories, 0, 2)];

        $remainingSlots = max(0, $limit - $this->countUrls($sections));
        if ($remainingSlots > 0) {
            // Walk the full catalog, skipping submitted/listed, until we fill
            // the remaining quota. Order by sort_order then newest.
            $featured = Product::where('is_active', true)
                ->orderBy('sort_order')
                ->orderByDesc('id')
                ->pluck('slug')
                ->map(fn ($s) => "{$base}/products/{$s}")
                ->all();
            $featured = array_slice($filter($featured), 0, $remainingSlots);
            if ($featured) $sections[] = ['Tier 3 · 主力商品（輪替）', $featured];
        }

        return $sections;
    }

    /** @param list<array{0:string, 1:list<string>}> $sections */
    private function emitStdout(array $sections): int
    {
        if ($this->option('flat')) {
            foreach ($sections as [, $urls]) {
                foreach ($urls as $u) $this->line($u);
            }
            return self::SUCCESS;
        }

        $this->info('GSC 今日索引清單 · ' . now()->format('Y-m-d'));
        $this->line('─' . str_repeat('─', 59));
        foreach ($sections as [$label, $urls]) {
            $this->line('');
            $this->info("{$label} ({$this->pluralize(count($urls))})");
            foreach ($urls as $u) $this->line("  {$u}");
        }
        $this->line('');
        $this->line(str_repeat('─', 60));
        $this->line("總計：{$this->countUrls($sections)} 個 URL");
        return self::SUCCESS;
    }

    /**
     * Insert today's new-URL section after the user's `---` separator
     * (or at top if the file is fresh). The user's own Day 1-5 plan stays
     * intact; we just add a dated block above it for auto-generated picks.
     *
     * @param list<array{0:string, 1:list<string>}> $sections
     */
    private function appendToChecklist(string $file, array $sections, string $host): void
    {
        $date = now()->format('Y-m-d');
        $time = now()->format('H:i');

        $newSection = "## 今日新增 · {$date}（產生於 {$time}）\n\n";
        if ($this->countUrls($sections) === 0) {
            $newSection .= "_今天沒有新 URL（過去全部已列出或已提交）。可等明天新商品/文章再試。_\n\n";
        } else {
            foreach ($sections as [$label, $urls]) {
                $newSection .= "### {$label}\n\n";
                foreach ($urls as $u) $newSection .= "- [ ] {$u}\n";
                $newSection .= "\n";
            }
        }

        if (! is_file($file)) {
            $header = "# GSC URL Submission Plan\n\n網站：`https://{$host}`\n\n---\n\n";
            file_put_contents($file, $header . $newSection);
            return;
        }

        $existing = file_get_contents($file);

        // If a previous "今日新增" block exists for the same day, replace it
        // in place. Otherwise insert after the first `---` separator.
        $todayPattern = '/## 今日新增 · ' . preg_quote($date, '/') . '.*?(?=\n## |\z)/s';
        if (preg_match($todayPattern, $existing)) {
            $updated = preg_replace($todayPattern, rtrim($newSection) . "\n\n", $existing);
        } else {
            // Find first "---" alone on a line. Insert the new section right
            // after it. If no separator, prepend to the whole file.
            if (preg_match('/^---\s*$/m', $existing, $m, PREG_OFFSET_CAPTURE)) {
                $insertAt = $m[0][1] + strlen($m[0][0]);
                $updated = substr($existing, 0, $insertAt) . "\n\n" . $newSection . ltrim(substr($existing, $insertAt));
            } else {
                $updated = $newSection . "\n" . $existing;
            }
        }

        file_put_contents($file, $updated);
    }

    /** @param list<array{0:string, 1:list<string>}> $sections */
    private function countUrls(array $sections): int
    {
        return array_sum(array_map(fn ($s) => count($s[1]), $sections));
    }

    /**
     * @param list<array{0:string, 1:list<string>}> $sections
     * @return list<array{0:string, 1:list<string>}>
     */
    private function trimToLimit(array $sections, int $limit): array
    {
        $result = [];
        $used = 0;
        foreach ($sections as [$label, $urls]) {
            $take = min(count($urls), $limit - $used);
            if ($take <= 0) break;
            $result[] = [$label, array_slice($urls, 0, $take)];
            $used += $take;
        }
        return $result;
    }

    private function pluralize(int $n): string
    {
        return "{$n} 條";
    }
}
