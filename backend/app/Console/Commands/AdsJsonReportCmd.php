<?php

namespace App\Console\Commands;

use App\Services\GoogleAdsService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

/**
 * Dumps Google Ads data as a single JSON blob on stdout.
 *
 * Designed to be piped to an LLM for deep analysis — includes everything
 * a human analyst needs: yesterday headline, 14-day series, top search
 * terms, wasteful terms, and a pre-computed baseline for comparison.
 *
 * Usage:
 *   php artisan ads:json-report                 # 14 days window
 *   php artisan ads:json-report --days=30       # wider window
 *   php artisan ads:json-report --pretty        # indented JSON
 */
class AdsJsonReportCmd extends Command
{
    protected $signature = 'ads:json-report
        {--days=14 : Rolling window for series + search terms}
        {--pretty : Pretty-print JSON (for human reading)}';

    protected $description = 'Dump Google Ads data as JSON for downstream analysis (LLM, notebook, etc.)';

    public function handle(): int
    {
        $ads = GoogleAdsService::fromConfig();
        $days = max(7, (int) $this->option('days'));

        if (! $ads->isConfigured()) {
            $this->outputJson(['configured' => false, 'error' => 'Google Ads API not configured']);
            return self::SUCCESS;
        }

        $yesterday = $ads->getYesterdayMetrics();
        $series    = $ads->getDailySeries($days);
        $topTerms  = $ads->getTopSearchTerms(days: 7, limit: 30);
        $wasteful  = $ads->getWastefulSearchTerms(days: 7, minSpend: 30, limit: 15);

        // Pre-compute baselines so the LLM doesn't have to
        $baseline = $this->computeBaseline($series, excludeLastDay: true);

        $prevWeek  = $this->sliceDays($series, -14, -7);
        $thisWeek  = $this->sliceDays($series, -7);
        $weekOverWeek = $this->compareWindows($prevWeek, $thisWeek);

        $payload = [
            'configured' => true,
            'generated_at' => now()->toIso8601String(),
            'business_context' => [
                'brand' => '婕樂纖 / JEROSSE / Fairy Pandora (FP)',
                'site' => 'https://pandora.js-store.com.tw',
                'market' => 'Taiwan',
                'currency' => 'TWD',
                'category' => '健康保健 / 美容保養（health supplements + beauty care）',
                'pricing_model' => '三階定價：原價 → 1+1 搭配價（任選 2 件） → VIP 價（滿 $4,000）',
                'typical_daily_budget_twd' => 500, // adjust per conversation with user
            ],
            'yesterday' => $yesterday,
            'baseline_prev_13d_avg' => $baseline,
            'week_over_week' => $weekOverWeek,
            'daily_series' => $series,
            'top_search_terms_7d' => $topTerms,
            'wasteful_terms_7d' => $wasteful,
            'metrics_glossary' => [
                'spend' => 'TWD integer (micros已換算)',
                'clicks' => 'clicks count',
                'impressions' => 'ad impressions',
                'conversions' => 'tracked conversion events (may be decimal if attribution-weighted)',
                'conversion_value' => 'TWD integer — revenue from tracked conversions',
                'ctr' => 'percentage (0–100)',
                'cpc' => 'TWD integer — cost per click',
                'cpa' => 'TWD integer — cost per acquisition',
                'roas' => 'ratio — conversion_value / spend',
            ],
        ];

        $this->outputJson($payload);
        return self::SUCCESS;
    }

    private function outputJson(array $data): void
    {
        $flags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;
        if ($this->option('pretty')) {
            $flags |= JSON_PRETTY_PRINT;
        }
        $this->line(json_encode($data, $flags));
    }

    /**
     * Average of daily metrics over the series (minus last day so we
     * don't contaminate the baseline with the day we're comparing).
     *
     * @param list<array{date:string, spend:int, clicks:int, impressions:int, conversions:float}> $series
     */
    private function computeBaseline(array $series, bool $excludeLastDay = false): array
    {
        if (empty($series)) {
            return ['spend' => 0, 'clicks' => 0, 'impressions' => 0, 'conversions' => 0, 'days' => 0];
        }

        $data = $excludeLastDay ? array_slice($series, 0, -1) : $series;
        $n = max(1, count($data));

        return [
            'days'        => count($data),
            'spend'       => (int) round(array_sum(array_column($data, 'spend')) / $n),
            'clicks'      => (int) round(array_sum(array_column($data, 'clicks')) / $n),
            'impressions' => (int) round(array_sum(array_column($data, 'impressions')) / $n),
            'conversions' => round(array_sum(array_column($data, 'conversions')) / $n, 2),
        ];
    }

    /** @param list<array> $series */
    private function sliceDays(array $series, int $from, ?int $to = null): array
    {
        return array_slice($series, $from, $to === null ? null : ($to - $from));
    }

    private function compareWindows(array $prev, array $current): array
    {
        $sum = fn ($arr, $k) => (int) array_sum(array_column($arr, $k));

        $pSpend = $sum($prev, 'spend');
        $cSpend = $sum($current, 'spend');
        $pConv  = array_sum(array_column($prev, 'conversions'));
        $cConv  = array_sum(array_column($current, 'conversions'));

        $pct = fn ($prev, $curr) => $prev > 0
            ? (int) round((($curr - $prev) / $prev) * 100)
            : null;

        return [
            'prev_7d'     => ['spend' => $pSpend, 'conversions' => round($pConv, 2)],
            'this_7d'     => ['spend' => $cSpend, 'conversions' => round($cConv, 2)],
            'spend_delta_pct'       => $pct($pSpend, $cSpend),
            'conversions_delta_pct' => $pct($pConv, $cConv),
        ];
    }
}
