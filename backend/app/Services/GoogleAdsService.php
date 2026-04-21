<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Google\Ads\GoogleAds\Lib\OAuth2TokenBuilder;
use Google\Ads\GoogleAds\Lib\V23\GoogleAdsClientBuilder;
use Google\Ads\GoogleAds\V23\Services\SearchGoogleAdsRequest;
use Illuminate\Support\Facades\Log;

/**
 * Read-only Google Ads API wrapper.
 *
 * Wraps the official SDK behind a small domain-specific surface so the
 * rest of the app never touches gRPC types. All money values are
 * converted from micros to integer TWD at this boundary.
 *
 * Graceful failure: if the API is misconfigured or down, methods return
 * sensible empty defaults and log the exception. The Filament widgets
 * and daily report should never hard-fail the whole page.
 */
class GoogleAdsService
{
    private const MICROS_PER_UNIT = 1_000_000;

    public function __construct(
        private readonly ?string $developerToken = null,
        private readonly ?string $clientId = null,
        private readonly ?string $clientSecret = null,
        private readonly ?string $refreshToken = null,
        private readonly ?string $customerId = null,
        private readonly ?string $loginCustomerId = null,
    ) {}

    public static function fromConfig(): self
    {
        $c = config('services.google_ads');
        return new self(
            developerToken:   $c['developer_token']    ?? null,
            clientId:         $c['client_id']          ?? null,
            clientSecret:     $c['client_secret']      ?? null,
            refreshToken:     $c['refresh_token']      ?? null,
            customerId:       $c['customer_id']        ?? null,
            loginCustomerId:  $c['login_customer_id']  ?? null,
        );
    }

    public function isConfigured(): bool
    {
        return filled($this->developerToken)
            && filled($this->clientId)
            && filled($this->clientSecret)
            && filled($this->refreshToken)
            && filled($this->customerId);
    }

    /**
     * Yesterday's headline metrics. Returns zeroes (not null) if API fails,
     * so Filament widgets can render without null-guards everywhere.
     *
     * @return array{spend:int, clicks:int, impressions:int, conversions:float, conversion_value:int, ctr:float, cpc:int, cpa:int, roas:float, date:string}
     */
    public function getYesterdayMetrics(): array
    {
        return $this->getMetricsForRange(
            CarbonImmutable::yesterday()->toDateString(),
            CarbonImmutable::yesterday()->toDateString(),
        );
    }

    /**
     * Aggregated metrics for an inclusive date range (YYYY-MM-DD strings).
     *
     * @return array{spend:int, clicks:int, impressions:int, conversions:float, conversion_value:int, ctr:float, cpc:int, cpa:int, roas:float, date:string}
     */
    public function getMetricsForRange(string $start, string $end): array
    {
        $empty = [
            'spend' => 0, 'clicks' => 0, 'impressions' => 0,
            'conversions' => 0.0, 'conversion_value' => 0,
            'ctr' => 0.0, 'cpc' => 0, 'cpa' => 0, 'roas' => 0.0,
            'date' => $start === $end ? $start : "{$start}..{$end}",
        ];

        if (! $this->isConfigured()) {
            return $empty;
        }

        $gaql = <<<GAQL
            SELECT
                metrics.cost_micros,
                metrics.clicks,
                metrics.impressions,
                metrics.conversions,
                metrics.conversions_value
            FROM customer
            WHERE segments.date BETWEEN '{$start}' AND '{$end}'
        GAQL;

        try {
            $rows = $this->query($gaql);

            $spendMicros = 0;
            $clicks = 0;
            $impressions = 0;
            $conversions = 0.0;
            $convValueMicros = 0;

            foreach ($rows as $row) {
                $m = $row->getMetrics();
                $spendMicros     += $m->getCostMicros();
                $clicks          += $m->getClicks();
                $impressions     += $m->getImpressions();
                $conversions     += $m->getConversions();
                // conversions_value is in account currency (not micros)
                $convValueMicros += (int) round($m->getConversionsValue() * self::MICROS_PER_UNIT);
            }

            $spend     = intdiv($spendMicros, self::MICROS_PER_UNIT);
            $convValue = intdiv($convValueMicros, self::MICROS_PER_UNIT);
            $ctr  = $impressions > 0 ? round(($clicks / $impressions) * 100, 2) : 0.0;
            $cpc  = $clicks > 0 ? (int) round($spend / $clicks) : 0;
            $cpa  = $conversions > 0 ? (int) round($spend / $conversions) : 0;
            $roas = $spend > 0 ? round($convValue / $spend, 2) : 0.0;

            return [
                'spend' => $spend,
                'clicks' => $clicks,
                'impressions' => $impressions,
                'conversions' => round($conversions, 2),
                'conversion_value' => $convValue,
                'ctr' => $ctr,
                'cpc' => $cpc,
                'cpa' => $cpa,
                'roas' => $roas,
                'date' => $empty['date'],
            ];
        } catch (\Throwable $e) {
            Log::warning('[google-ads] getMetricsForRange failed', ['msg' => $e->getMessage()]);
            return $empty;
        }
    }

    /**
     * Daily time series (one row per day) for a rolling window.
     * Useful for sparkline widgets and period-over-period charts.
     *
     * @return list<array{date:string, spend:int, clicks:int, impressions:int, conversions:float}>
     */
    public function getDailySeries(int $days = 30): array
    {
        if (! $this->isConfigured()) {
            return [];
        }

        $end   = CarbonImmutable::yesterday()->toDateString();
        $start = CarbonImmutable::yesterday()->subDays($days - 1)->toDateString();

        $gaql = <<<GAQL
            SELECT
                segments.date,
                metrics.cost_micros,
                metrics.clicks,
                metrics.impressions,
                metrics.conversions
            FROM customer
            WHERE segments.date BETWEEN '{$start}' AND '{$end}'
            ORDER BY segments.date ASC
        GAQL;

        try {
            $rows = $this->query($gaql);
            $out = [];
            foreach ($rows as $row) {
                $m = $row->getMetrics();
                $out[] = [
                    'date'        => $row->getSegments()->getDate(),
                    'spend'       => intdiv($m->getCostMicros(), self::MICROS_PER_UNIT),
                    'clicks'      => $m->getClicks(),
                    'impressions' => $m->getImpressions(),
                    'conversions' => round($m->getConversions(), 2),
                ];
            }
            return $out;
        } catch (\Throwable $e) {
            Log::warning('[google-ads] getDailySeries failed', ['msg' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Top search terms (what users actually typed) in a rolling window.
     * Sorted by highest spend. Filters to terms with ≥ 1 click.
     *
     * @return list<array{term:string, spend:int, clicks:int, impressions:int, conversions:float}>
     */
    public function getTopSearchTerms(int $days = 7, int $limit = 15): array
    {
        if (! $this->isConfigured()) {
            return [];
        }

        $end   = CarbonImmutable::yesterday()->toDateString();
        $start = CarbonImmutable::yesterday()->subDays($days - 1)->toDateString();

        $gaql = <<<GAQL
            SELECT
                search_term_view.search_term,
                metrics.cost_micros,
                metrics.clicks,
                metrics.impressions,
                metrics.conversions
            FROM search_term_view
            WHERE segments.date BETWEEN '{$start}' AND '{$end}'
                AND metrics.clicks > 0
            ORDER BY metrics.cost_micros DESC
            LIMIT {$limit}
        GAQL;

        try {
            $rows = $this->query($gaql);
            $out = [];
            foreach ($rows as $row) {
                $m = $row->getMetrics();
                $out[] = [
                    'term'        => $row->getSearchTermView()->getSearchTerm(),
                    'spend'       => intdiv($m->getCostMicros(), self::MICROS_PER_UNIT),
                    'clicks'      => $m->getClicks(),
                    'impressions' => $m->getImpressions(),
                    'conversions' => round($m->getConversions(), 2),
                ];
            }
            return $out;
        } catch (\Throwable $e) {
            Log::warning('[google-ads] getTopSearchTerms failed', ['msg' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Wasteful search terms: spent money, produced zero conversions, and are
     * NOT brand-name queries. Brand queries with zero conversions are not
     * waste — they're people searching for the store by name and comparing,
     * and adding them as negatives would kill your most intent-rich traffic.
     *
     * @return list<array{term:string, spend:int, clicks:int}>
     */
    public function getWastefulSearchTerms(int $days = 7, int $minSpend = 50, int $limit = 10): array
    {
        $terms = $this->getTopSearchTerms($days, 100);
        $waste = array_values(array_filter($terms, fn ($t) =>
            $t['spend'] >= $minSpend
            && $t['conversions'] == 0
            && ! self::isBrandTerm($t['term'])
        ));
        return array_slice($waste, 0, $limit);
    }

    /**
     * Heuristic brand-term detector. Google's search-term report normalizes
     * with spaces between Chinese characters, so we strip whitespace before
     * comparing. Extend $needles as new brand variations appear.
     */
    public static function isBrandTerm(string $term): bool
    {
        $normalized = mb_strtolower(preg_replace('/\s+/u', '', $term));
        $needles = [
            '婕樂纖',     // 主品牌
            '捷樂纖',     // 常見錯字
            'jerosse',    // 英文品牌
            'fairypandora',
            'fp仙女館',
            '仙女館',
            '法樂蓬',     // 旗下產品線
            '婕楽纖',     // 異體字錯字
        ];
        foreach ($needles as $n) {
            if (str_contains($normalized, $n)) return true;
        }
        return false;
    }

    // ────────────────────────────────────────────────────────────
    // Internals
    // ────────────────────────────────────────────────────────────

    /**
     * Run a GAQL query and return an iterable of GoogleAdsRow.
     *
     * @return iterable<\Google\Ads\GoogleAds\V23\Services\GoogleAdsRow>
     */
    private function query(string $gaql): iterable
    {
        $client = $this->buildClient();

        $request = SearchGoogleAdsRequest::build($this->customerId, $gaql);
        $response = $client->getGoogleAdsServiceClient()->search($request);

        // PagedListResponse is iterable; each item is a GoogleAdsRow
        foreach ($response->iterateAllElements() as $row) {
            yield $row;
        }
    }

    private function buildClient(): \Google\Ads\GoogleAds\Lib\V23\GoogleAdsClient
    {
        $oauth = (new OAuth2TokenBuilder())
            ->withClientId($this->clientId)
            ->withClientSecret($this->clientSecret)
            ->withRefreshToken($this->refreshToken)
            ->build();

        $builder = (new GoogleAdsClientBuilder())
            ->withDeveloperToken($this->developerToken)
            ->withOAuth2Credential($oauth);

        if ($this->loginCustomerId) {
            $builder = $builder->withLoginCustomerId((int) $this->loginCustomerId);
        }

        return $builder->build();
    }
}
