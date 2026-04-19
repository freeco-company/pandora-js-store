<?php

namespace App\Console\Commands;

use App\Models\Article;
use App\Models\Product;
use App\Models\SeoMetric;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Weekly Core Web Vitals snapshot via Google PageSpeed Insights API.
 *
 * Tracks 3 representative URLs (homepage + a hot product + a hot article)
 * on the mobile profile, persists to seo_metrics_weekly, and lets the
 * Filament dashboard render trend lines so we notice regressions weeks
 * before users complain.
 *
 * Why these 3 URLs: home is the brand-keyword landing surface; product
 * detail is the conversion surface; article detail is the long-tail SEO
 * surface. Together they cover the page templates that matter for org traffic.
 *
 * Auth: uses PAGESPEED_API_KEY env (free 25k/day from Google Cloud Console).
 * Without a key the command no-ops cleanly with a warning so cron stays green.
 */
class SeoWeeklySnapshotCmd extends Command
{
    protected $signature = 'seo:weekly-snapshot {--strategy=mobile : mobile|desktop}';
    protected $description = 'Capture PageSpeed + Core Web Vitals for representative URLs';

    public function handle(): int
    {
        $apiKey = env('PAGESPEED_API_KEY');
        if (!$apiKey) {
            $this->warn('PAGESPEED_API_KEY not set — skipping. Add the key to enable weekly SEO snapshots.');
            return self::SUCCESS;
        }

        $base = rtrim(config('services.ecpay.frontend_url', 'https://pandora.js-store.com.tw'), '/');
        $strategy = $this->option('strategy');

        // Pick a representative product + article (most-recently-updated active ones).
        $hotProduct = Product::where('is_active', true)
            ->orderByDesc('updated_at')->first();
        $hotArticle = Article::where('is_published', true)
            ->orderByDesc('published_at')->first();

        $targets = array_filter([
            ['label' => 'home', 'url' => $base],
            $hotProduct ? ['label' => 'product_detail', 'url' => "{$base}/products/{$hotProduct->slug}"] : null,
            $hotArticle ? ['label' => 'article_detail', 'url' => "{$base}/articles/{$hotArticle->slug}"] : null,
        ]);

        $today = now()->toDateString();
        $stored = 0;
        $errors = 0;

        foreach ($targets as $t) {
            try {
                $row = $this->measure($t['url'], $strategy, $apiKey);
                if (!$row) { $errors++; continue; }

                SeoMetric::updateOrCreate(
                    ['measured_on' => $today, 'label' => $t['label'], 'strategy' => $strategy],
                    [
                        'url' => $t['url'],
                        'perf_score'  => $row['perf_score'],
                        'lcp_ms'      => $row['lcp_ms'],
                        'cls_x1000'   => $row['cls_x1000'],
                        'tbt_ms'      => $row['tbt_ms'],
                        'inp_ms'      => $row['inp_ms'],
                        'opportunities' => $row['opportunities'],
                    ],
                );
                $stored++;
            } catch (\Throwable $e) {
                Log::warning('[seo:weekly-snapshot] failed', ['url' => $t['url'], 'msg' => $e->getMessage()]);
                $errors++;
            }
        }

        $this->info("stored {$stored} / {$strategy} · errors {$errors}");
        return self::SUCCESS;
    }

    /**
     * @return array{perf_score:?int,lcp_ms:?int,cls_x1000:?int,tbt_ms:?int,inp_ms:?int,opportunities:array}|null
     */
    private function measure(string $url, string $strategy, string $apiKey): ?array
    {
        $res = Http::timeout(60)
            ->retry(2, 2000, throw: false)
            ->get('https://www.googleapis.com/pagespeedonline/v5/runPagespeed', [
                'url' => $url,
                'strategy' => $strategy,
                'category' => ['performance'],
                'key' => $apiKey,
            ]);

        if (!$res->successful()) return null;

        $audits = $res->json('lighthouseResult.audits') ?? [];
        $score = $res->json('lighthouseResult.categories.performance.score');
        $loadingExperience = $res->json('loadingExperience.metrics') ?? [];

        // Top 3 actionable opportunities — sort by displayValue size as proxy
        $opportunities = collect($audits)
            ->filter(fn ($a) => ($a['details']['type'] ?? null) === 'opportunity'
                && ($a['numericValue'] ?? 0) > 100)
            ->sortByDesc('numericValue')
            ->take(3)
            ->map(fn ($a) => ['title' => $a['title'] ?? '', 'savings_ms' => (int) ($a['numericValue'] ?? 0)])
            ->values()
            ->all();

        return [
            'perf_score' => $score !== null ? (int) round($score * 100) : null,
            'lcp_ms'     => isset($audits['largest-contentful-paint']['numericValue'])
                ? (int) round($audits['largest-contentful-paint']['numericValue']) : null,
            'cls_x1000'  => isset($audits['cumulative-layout-shift']['numericValue'])
                ? (int) round($audits['cumulative-layout-shift']['numericValue'] * 1000) : null,
            'tbt_ms'     => isset($audits['total-blocking-time']['numericValue'])
                ? (int) round($audits['total-blocking-time']['numericValue']) : null,
            // Field INP from CrUX, when available
            'inp_ms'     => $loadingExperience['INTERACTION_TO_NEXT_PAINT']['percentile'] ?? null,
            'opportunities' => $opportunities,
        ];
    }
}
