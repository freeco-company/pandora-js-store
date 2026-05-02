<?php

namespace App\Console\Commands;

use App\Services\DiscordNotifier;
use App\Services\GoogleAdsService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 「管線健診」每日報告 — 2 週新站專用版。
 *
 * 取代舊的 ads:daily-report 那份只看 Google Ads 的視角。這份把四條管線
 * 揉在一起看，一則 Discord 訊息內回答：
 *
 *   1. 流量有來嗎？（廣告 / 自然 / AI 引薦 / 直接）
 *   2. 有人加入購物車 / 走到結帳嗎？（用 orders.status=pending 當代理）
 *   3. 有人付款嗎？（連續 0 轉天數 — 新站 ≤ 7 天算正常）
 *   4. SEO / GEO 有在長大嗎？（14 日 organic 趨勢 / AI 爬取活躍度）
 *
 * 新站（< 30 天）容錯邏輯：0 轉換在 ≤ 7 天內顯示黃燈不是紅燈；流量小樣本
 * 下 DoD 比較改為 vs 7 日均（噪音小）。
 *
 * 09:10 Asia/Taipei 排程，接 DISCORD_ADS_WEBHOOK。
 */
class PipelineDailyReportCmd extends Command
{
    protected $signature = 'pipeline:daily-report {--dry : Print locally without posting}';

    protected $description = 'Aggregate flow/funnel/SEO/GEO/Ads into a single Discord pipeline-health report.';

    /** 新站容錯門檻 — 連續這麼多天 0 轉換仍算可容忍。 */
    private const NEW_SITE_ZERO_CONVERSION_TOLERANCE_DAYS = 7;

    public function handle(): int
    {
        $yesterday = today()->subDay();
        $sevenDaysAgo = today()->subDays(7);
        $fourteenDaysAgo = today()->subDays(14);

        $flow    = $this->flowMetrics($yesterday, $sevenDaysAgo, $fourteenDaysAgo);
        $funnel  = $this->funnelMetrics($yesterday, $flow['yesterdayUv']);
        $seo     = $this->seoTrend($fourteenDaysAgo);
        $geo     = $this->geoActivity($yesterday);
        $ads     = $this->adsMetrics();
        $lights  = $this->healthLights($flow, $funnel, $seo, $geo);

        [$title, $description, $fields, $color] = $this->buildEmbed(
            $yesterday, $lights, $flow, $funnel, $seo, $geo, $ads,
        );

        if ($this->option('dry')) {
            $this->line("=== {$title} ===");
            $this->line($description);
            foreach ($fields as $f) {
                $this->line('');
                $this->line("### {$f['name']}");
                $this->line($f['value']);
            }
            return self::SUCCESS;
        }

        $notifier = DiscordNotifier::ads();
        if (! $notifier->isEnabled()) {
            $this->warn('DISCORD_ADS_WEBHOOK not set. Skipping post.');
            return self::SUCCESS;
        }

        return $notifier->embed($title, $description, $fields, $color)
            ? self::SUCCESS
            : self::FAILURE;
    }

    // ─── Data gatherers ──────────────────────────────────────────────────

    /**
     * Human UV by source bucket — yesterday + 7-day avg + 7-day sum.
     * Bot rows excluded (they're counted separately under GEO).
     */
    private function flowMetrics(Carbon $yesterday, Carbon $sevenDaysAgo, Carbon $fourteenDaysAgo): array
    {
        $q = fn (Carbon $s, Carbon $e) => DB::table('visits')
            ->whereBetween('visited_at', [$s->copy()->startOfDay(), $e->copy()->endOfDay()])
            ->where('referer_source', '!=', 'bot')
            ->where('is_internal', false);

        $paid = ['google_ads', 'facebook_ads', 'bing_ads', 'tiktok_ads', 'linkedin_ads', 'other_ads'];

        $yesterdayUv = (int) (clone $q($yesterday, $yesterday))->distinct('visitor_id')->count('visitor_id');
        $yesterdayPaid = (int) (clone $q($yesterday, $yesterday))->whereIn('referer_source', $paid)->distinct('visitor_id')->count('visitor_id');
        $yesterdayAi = (int) (clone $q($yesterday, $yesterday))->where('referer_source', 'ai_referral')->distinct('visitor_id')->count('visitor_id');
        $yesterdayOrganic = max(0, $yesterdayUv - $yesterdayPaid - $yesterdayAi);

        // 7-day average (excluding yesterday) — baseline for DoD comparison
        // without the noise of single-day spikes on a small-sample site.
        $sevenDayUv = (int) (clone $q($sevenDaysAgo, $yesterday->copy()->subDay()))->distinct('visitor_id')->count('visitor_id');
        $sevenDayAvg = (int) round($sevenDayUv / 7);

        // This week vs previous 7 days (WoW) — more useful than DoD on a low-volume site.
        $thisWeekUv = (int) (clone $q($sevenDaysAgo, $yesterday))->distinct('visitor_id')->count('visitor_id');
        $prevWeekUv = (int) (clone $q($fourteenDaysAgo, $sevenDaysAgo->copy()->subDay()))->distinct('visitor_id')->count('visitor_id');
        $wowPct = $prevWeekUv > 0 ? round((($thisWeekUv - $prevWeekUv) / $prevWeekUv) * 100) : null;

        // Organic-specific WoW — is SEO genuinely growing?
        $thisWeekOrganic = (int) (clone $q($sevenDaysAgo, $yesterday))
            ->whereNotIn('referer_source', array_merge($paid, ['ai_referral']))
            ->distinct('visitor_id')->count('visitor_id');
        $prevWeekOrganic = (int) (clone $q($fourteenDaysAgo, $sevenDaysAgo->copy()->subDay()))
            ->whereNotIn('referer_source', array_merge($paid, ['ai_referral']))
            ->distinct('visitor_id')->count('visitor_id');
        $organicWowPct = $prevWeekOrganic > 0 ? round((($thisWeekOrganic - $prevWeekOrganic) / $prevWeekOrganic) * 100) : null;

        return compact(
            'yesterdayUv', 'yesterdayPaid', 'yesterdayAi', 'yesterdayOrganic',
            'sevenDayAvg', 'thisWeekUv', 'prevWeekUv', 'wowPct',
            'thisWeekOrganic', 'prevWeekOrganic', 'organicWowPct',
        );
    }

    /**
     * Funnel — what proxy signals do we have for "people trying to buy"?
     *
     * Orders table tells us: who reached checkout (status=pending = abandoned
     * payment) and who completed (status in completed/processing/shipped).
     * Can't see add-to-cart from backend yet — that's only in GTM dataLayer.
     */
    private function funnelMetrics(Carbon $yesterday, int $yesterdayUv): array
    {
        $ordersYesterday = (int) DB::table('orders')
            ->whereBetween('created_at', [$yesterday->copy()->startOfDay(), $yesterday->copy()->endOfDay()])
            ->count();
        $pendingYesterday = (int) DB::table('orders')
            ->whereBetween('created_at', [$yesterday->copy()->startOfDay(), $yesterday->copy()->endOfDay()])
            ->whereIn('status', ['pending'])
            ->count();
        $completedYesterday = (int) DB::table('orders')
            ->whereBetween('created_at', [$yesterday->copy()->startOfDay(), $yesterday->copy()->endOfDay()])
            ->whereIn('status', ['completed', 'processing', 'shipped'])
            ->count();

        // Days since last real (paid) conversion — the one metric that matters
        // for a 2-week-old site. Exclude WP-imported historical orders since
        // they predate the current site's conversion funnel.
        $lastPaid = DB::table('orders')
            ->whereIn('status', ['completed', 'processing', 'shipped'])
            ->where('payment_status', 'paid')
            ->whereNull('wp_order_id') // only native (post-launch) orders
            ->orderByDesc('created_at')
            ->first(['created_at']);
        // Normalize both sides to start-of-day before diffing so "yesterday
        // 23:00" and "yesterday 01:00" both register as "1 day ago" — Carbon
        // otherwise returns 0 for fresh same-day rows and 2 for older ones,
        // which then confuses the new-site-tolerance threshold logic.
        // Carbon 3's diffInDays() is signed by default (past dates return
        // negative), which broke the new-site tolerance branches below.
        // abs() is cheaper than remembering to pass absolute:true everywhere.
        $daysSinceConversion = $lastPaid
            ? (int) abs(today()->diffInDays(Carbon::parse($lastPaid->created_at)->startOfDay()))
            : null;

        // True funnel rates from cart_events. Counts distinct sessions at each
        // stage so we can see where the funnel leaks. Schema-tolerant — if the
        // cart_events table doesn't exist yet (fresh migration), return nulls
        // so the report still renders cleanly.
        $cartEvents = $this->cartFunnelRates($yesterday, $yesterdayUv);

        return compact(
            'ordersYesterday', 'pendingYesterday', 'completedYesterday', 'daysSinceConversion',
        ) + $cartEvents;
    }

    /**
     * Unique-session counts at each funnel stage for yesterday, from
     * cart_events. Returns nulls (not zeros) when the table is empty so the
     * report can distinguish "no data yet" from "funnel is broken".
     */
    private function cartFunnelRates(Carbon $yesterday, int $yesterdayUv): array
    {
        if (! Schema::hasTable('cart_events')) {
            return [
                'viewItemSessions' => null,
                'addToCartSessions' => null,
                'beginCheckoutSessions' => null,
                'addToCartRate' => null,
            ];
        }

        $start = $yesterday->copy()->startOfDay();
        $end = $yesterday->copy()->endOfDay();

        $countBy = fn (string $eventType) => (int) DB::table('cart_events')
            ->whereBetween('occurred_at', [$start, $end])
            ->where('event_type', $eventType)
            ->where('is_internal', false)
            ->distinct('session_id')
            ->count('session_id');

        $view = $countBy('view_item');
        $add  = $countBy('add_to_cart');
        $ck   = $countBy('begin_checkout');

        // Any activity at all? If 0 on everything, data hasn't flowed yet —
        // return null so the report shows "N/A" rather than misleading 0%.
        $totalEvents = $view + $add + $ck;
        if ($totalEvents === 0) {
            return [
                'viewItemSessions' => null,
                'addToCartSessions' => null,
                'beginCheckoutSessions' => null,
                'addToCartRate' => null,
            ];
        }

        $addToCartRate = $yesterdayUv > 0
            ? round(($add / $yesterdayUv) * 100, 1)
            : null;

        return [
            'viewItemSessions' => $view,
            'addToCartSessions' => $add,
            'beginCheckoutSessions' => $ck,
            'addToCartRate' => $addToCartRate,
        ];
    }

    /** 14-day organic UV for a tiny ASCII sparkline. */
    private function seoTrend(Carbon $fourteenDaysAgo): array
    {
        $paid = ['google_ads', 'facebook_ads', 'bing_ads', 'tiktok_ads', 'linkedin_ads', 'other_ads'];
        $daily = DB::table('visits')
            ->selectRaw('DATE(visited_at) as d, COUNT(DISTINCT visitor_id) as uv')
            ->where('visited_at', '>=', $fourteenDaysAgo->copy()->startOfDay())
            ->where('visited_at', '<=', today()->subDay()->endOfDay())
            ->where('referer_source', '!=', 'bot')
            ->where('is_internal', false)
            ->whereNotIn('referer_source', array_merge($paid, ['ai_referral']))
            ->groupBy('d')->orderBy('d')
            ->pluck('uv', 'd')
            ->toArray();

        $series = [];
        $cursor = $fourteenDaysAgo->copy();
        $end = today()->subDay();
        while ($cursor <= $end) {
            $key = $cursor->toDateString();
            $series[$key] = (int) ($daily[$key] ?? 0);
            $cursor->addDay();
        }

        return ['series' => $series];
    }

    /**
     * AI crawler activity (last 4 days) from ai_visits_daily. This is the
     * "indexed / seen by AI" signal — not the same as being cited in answers.
     * True citation signal is ai_referral UV in flowMetrics().
     */
    private function geoActivity(Carbon $yesterday): array
    {
        $threshold = $yesterday->copy()->subDays(3)->toDateString();
        $rows = DB::table('ai_visits_daily')
            ->where('date', '>=', $threshold)
            ->selectRaw('bot_type, SUM(hits) as hits')
            ->groupBy('bot_type')
            ->orderByDesc('hits')
            ->get();

        $byBot = [];
        foreach ($rows as $r) {
            $byBot[$r->bot_type] = (int) $r->hits;
        }

        return ['by_bot' => $byBot, 'total' => array_sum($byBot)];
    }

    private function adsMetrics(): array
    {
        $ads = GoogleAdsService::fromConfig();
        if (! $ads->isConfigured()) {
            return ['configured' => false];
        }
        try {
            return [
                'configured' => true,
                'metrics'    => $ads->getYesterdayMetrics(),
                'waste'      => $ads->getWastefulSearchTerms(days: 7, minSpend: 50, limit: 3),
            ];
        } catch (\Throwable $e) {
            return ['configured' => true, 'error' => $e->getMessage()];
        }
    }

    /**
     * 4-light health status. Order matches the header's display order:
     * flow / SEO / GEO / conversion.
     *
     * @return array{lights:string, flow:string, seo:string, geo:string, conv:string}
     */
    private function healthLights(array $flow, array $funnel, array $seo, array $geo): array
    {
        // Flow: WoW UV growth. ≥ -5% green, ≥ -20% yellow, else red.
        $flowLight = match (true) {
            $flow['wowPct'] === null => '⚪',
            $flow['wowPct'] >= -5    => '🟢',
            $flow['wowPct'] >= -20   => '🟡',
            default                  => '🔴',
        };

        // SEO: organic WoW. Needs prev-week baseline to evaluate.
        $seoLight = match (true) {
            $flow['organicWowPct'] === null => '⚪',
            $flow['organicWowPct'] >= 10    => '🟢',
            $flow['organicWowPct'] >= -10   => '🟡',
            default                         => '🔴',
        };

        // GEO: AI referral UV. Any real human clickthrough from AI = green.
        // Bot crawling alone = yellow (indexed but not cited).
        $geoLight = match (true) {
            $flow['yesterdayAi'] >= 1    => '🟢',
            $geo['total']        > 0     => '🟡',
            default                      => '⚪',
        };

        // Conversion: days since last paid order, with new-site tolerance.
        $convLight = match (true) {
            $funnel['daysSinceConversion'] === null                          => '🔴', // never
            $funnel['daysSinceConversion'] <= 1                              => '🟢',
            $funnel['daysSinceConversion'] <= self::NEW_SITE_ZERO_CONVERSION_TOLERANCE_DAYS => '🟡',
            default                                                          => '🔴',
        };

        return [
            'lights' => "{$flowLight}{$seoLight}{$geoLight}{$convLight}",
            'flow'   => $flowLight,
            'seo'    => $seoLight,
            'geo'    => $geoLight,
            'conv'   => $convLight,
        ];
    }

    // ─── Formatters ──────────────────────────────────────────────────────

    /**
     * @return array{0:string, 1:string, 2:list<array{name:string, value:string, inline?:bool}>, 3:int}
     */
    private function buildEmbed(
        Carbon $yesterday,
        array $lights,
        array $flow,
        array $funnel,
        array $seo,
        array $geo,
        array $ads,
    ): array {
        $title = sprintf('📊 管線健診 · %s', $yesterday->format('Y-m-d (D)'));

        $description = sprintf(
            "管線接通度 %s\n流量 %s · SEO %s · GEO %s · 轉換 %s\n%s",
            $lights['lights'],
            $lights['flow'], $lights['seo'], $lights['geo'], $lights['conv'],
            $this->oneLiner($flow, $funnel),
        );

        $fields = [
            $this->flowField($flow),
            $this->funnelField($funnel, $flow),
            $this->seoField($seo, $flow),
            $this->geoField($geo, $flow),
        ];
        if ($ads['configured'] ?? false) {
            $fields[] = $this->adsField($ads);
        }
        $fields[] = $this->actionField($flow, $funnel, $seo, $geo, $ads);

        // Green if conversion light green, amber if red conversion, else brand.
        $color = match ($lights['conv']) {
            '🟢'    => 3066993,
            '🔴'    => 15158332,
            '🟡'    => 15844367,
            default => 10447166,
        };

        return [$title, $description, $fields, $color];
    }

    private function oneLiner(array $flow, array $funnel): string
    {
        $wow = $flow['wowPct'] !== null ? ($flow['wowPct'] >= 0 ? '+' : '') . $flow['wowPct'] . '%' : '—';
        // Report runs 09:10 about yesterday. A row from "12 hours ago" ends up
        // as 1 day by calendar diff, which is the "healthy" state for a daily
        // cadence — so 0-1 days both read as "yesterday had an order".
        $daysLabel = match (true) {
            $funnel['daysSinceConversion'] === null => '尚未首單',
            $funnel['daysSinceConversion'] <= 1     => '昨日有單 ✓',
            default                                  => "已 {$funnel['daysSinceConversion']} 天無成交",
        };

        return sprintf(
            '昨日 %d UV · 本週 vs 上週 %s · 轉換：%s',
            $flow['yesterdayUv'], $wow, $daysLabel,
        );
    }

    private function flowField(array $flow): array
    {
        $value = sprintf(
            "```\n昨日 UV   %3d  (7日均 %d)\n  廣告    %3d\n  自然    %3d\n  AI引薦  %3d\n\n本週 UV  %3d (%s vs 上週 %d)\n本週自然 %3d (%s vs 上週 %d)\n```",
            $flow['yesterdayUv'], $flow['sevenDayAvg'],
            $flow['yesterdayPaid'], $flow['yesterdayOrganic'], $flow['yesterdayAi'],
            $flow['thisWeekUv'], $this->pct($flow['wowPct']), $flow['prevWeekUv'],
            $flow['thisWeekOrganic'], $this->pct($flow['organicWowPct']), $flow['prevWeekOrganic'],
        );
        return ['name' => '📈 流量（人類訪客）', 'value' => $value];
    }

    private function funnelField(array $funnel, array $flow): array
    {
        $notes = [];
        if ($funnel['daysSinceConversion'] === null) {
            $notes[] = '⚠ 從未成交 — 新站需驗證結帳流程';
        } elseif ($funnel['daysSinceConversion'] > self::NEW_SITE_ZERO_CONVERSION_TOLERANCE_DAYS) {
            $notes[] = sprintf('🚨 連續 %d 天無成交 — 超過新站容忍值（%d天）', $funnel['daysSinceConversion'], self::NEW_SITE_ZERO_CONVERSION_TOLERANCE_DAYS);
        } elseif ($funnel['daysSinceConversion'] > 0) {
            $notes[] = sprintf('⏳ 已 %d 天無成交（新站 ≤ %d 天仍算可接受）', $funnel['daysSinceConversion'], self::NEW_SITE_ZERO_CONVERSION_TOLERANCE_DAYS);
        }

        // Pipeline diagnostics from cart_events — points at exactly WHERE the
        // funnel breaks. If add_to_cart rate is below industry baseline
        // (~5% for TW ecommerce), landing pages / product CTAs are the issue.
        // If add-to-cart is healthy but begin_checkout is low, the cart page
        // or shipping-fee shock is the issue.
        if ($funnel['addToCartSessions'] !== null) {
            if ($flow['yesterdayUv'] >= 20 && $funnel['addToCartRate'] !== null && $funnel['addToCartRate'] < 2) {
                $notes[] = sprintf(
                    '⚠ 加購率僅 %.1f%%（業界 5-10%%）— 商品頁/CTA 需優化',
                    $funnel['addToCartRate'],
                );
            }
            if ($funnel['addToCartSessions'] > 0 && $funnel['beginCheckoutSessions'] === 0) {
                $notes[] = sprintf(
                    '⚠ 有 %d session 加購但 0 進結帳 — 購物車或運費環節斷',
                    $funnel['addToCartSessions'],
                );
            }
        } elseif ($flow['yesterdayUv'] >= 20 && $funnel['ordersYesterday'] === 0) {
            // Fallback path for when cart_events hasn't been populated yet.
            $notes[] = sprintf('⚠ %d UV 但 0 進結帳 — 落地頁/加購 CTA 要檢查', $flow['yesterdayUv']);
        }

        // Body: show cart-event funnel if we have it, otherwise just orders.
        if ($funnel['addToCartSessions'] !== null) {
            $rateLine = $funnel['addToCartRate'] !== null
                ? sprintf('（加購率 %.1f%%）', $funnel['addToCartRate'])
                : '';
            $body = sprintf(
                "```\n昨日 UV          %d\n  └ 看商品       %d session\n  └ 加入購物車   %d session %s\n  └ 進入結帳     %d session\n  └ 成功付款     %d 筆\n```",
                $flow['yesterdayUv'],
                $funnel['viewItemSessions'] ?? 0,
                $funnel['addToCartSessions'],
                $rateLine,
                $funnel['beginCheckoutSessions'] ?? 0,
                $funnel['completedYesterday'],
            );
        } else {
            $body = sprintf(
                "```\n昨日進入結帳   %d 筆\n  └ 已付款      %d\n  └ 待付款(棄)  %d\n```\n（加購明細尚無資料 — cart_events 追蹤 2026-04-23 上線）",
                $funnel['ordersYesterday'], $funnel['completedYesterday'], $funnel['pendingYesterday'],
            );
        }

        $note = $notes ? "\n" . implode("\n", $notes) : '';
        return ['name' => '🎯 轉換漏斗', 'value' => $body . $note];
    }

    private function seoField(array $seo, array $flow): array
    {
        $series = $seo['series'];
        $max = max(1, ...array_values($series));
        $lines = [];
        foreach ($series as $date => $uv) {
            $bar = $uv > 0 ? str_repeat('█', max(1, (int) round($uv / $max * 15))) : '·';
            $lines[] = sprintf('%s  %s %d', substr($date, 5), $bar, $uv);
        }
        $value = "```\n" . implode("\n", $lines) . "\n```";

        if ($flow['organicWowPct'] !== null) {
            $verdict = $flow['organicWowPct'] >= 10 ? '📈 成長中 — 方向對' :
                ($flow['organicWowPct'] >= -10 ? '━ 持平' : '📉 下滑中 — 需調查');
            $value .= "\n{$verdict}（GSC 預計第 4 週接）";
        }

        return ['name' => '🌱 SEO（自然搜尋 + 直接進站 14 日）', 'value' => $value];
    }

    private function geoField(array $geo, array $flow): array
    {
        $labels = [
            'claude'     => 'Claude',
            'gpt'        => 'GPT',
            'google_ai'  => 'GoogleAI',
            'perplexity' => 'Perplex',
            'meta'       => 'Meta',
            'apple'      => 'Apple',
            'bytedance'  => 'ByteDance',
        ];
        $botLines = [];
        foreach ($geo['by_bot'] as $type => $hits) {
            $label = $labels[$type] ?? $type;
            $botLines[] = sprintf('  %-10s %d hits', $label, $hits);
        }
        if (! $botLines) $botLines[] = '  (近 4 日無 AI 爬蟲活動)';

        $citation = $flow['yesterdayAi'] > 0
            ? sprintf("✅ 昨日 AI 引薦 UV：%d — 代表 AI 在答題時有引用你", $flow['yesterdayAi'])
            : '⚪ 昨日 AI 引薦 UV：0 — 爬蟲有爬但尚未在答題引用';

        $value = "**AI 爬取（近 4 日聚合，= 被索引）**\n```\n"
            . implode("\n", $botLines)
            . "\n```\n" . $citation;

        return ['name' => '🤖 GEO（生成式搜尋能見度）', 'value' => $value];
    }

    private function adsField(array $ads): array
    {
        if (isset($ads['error'])) {
            return ['name' => '💰 Google Ads', 'value' => '⚠ API 暫時錯誤：' . substr($ads['error'], 0, 200)];
        }

        $m = $ads['metrics'];
        $spend = number_format($m['spend']);
        $clicks = number_format($m['clicks']);
        $conv = $m['conversions'];
        $cpc = $m['cpc'] > 0 ? 'NT$' . number_format($m['cpc']) : '—';
        $roas = $m['roas'] > 0 ? "{$m['roas']}x" : '—';

        $value = sprintf(
            "```\n花費 NT\$%s  點擊 %s  CTR %s%%\nCPC  %s     轉換 %s   ROAS %s\n```",
            $spend, $clicks, $m['ctr'], $cpc, $conv, $roas,
        );

        if (! empty($ads['waste'])) {
            $waste = array_map(
                fn ($w, $i) => sprintf(
                    '%d. `%s` — NT$%s / %s 點擊',
                    $i + 1, mb_strimwidth($w['term'], 0, 30, '…'),
                    number_format($w['spend']), number_format($w['clicks']),
                ),
                $ads['waste'], array_keys($ads['waste']),
            );
            $value .= "\n**燒錢無效關鍵字（非品牌，近 7 天）**\n" . implode("\n", $waste);
        }

        return ['name' => '💰 Google Ads（昨日 / 近 7 天浪費字）', 'value' => $value];
    }

    /**
     * Generate prioritized actions. Returned as raw strings without a leading
     * bullet; the wrapper prepends ①②③④ in order, so counts stay consistent
     * regardless of which rules fired.
     */
    private function actionField(array $flow, array $funnel, array $seo, array $geo, array $ads): array
    {
        $actions = [];

        // Priority 1: conversion blockers (trumps everything). Drill down
        // using cart_events when available to point at the broken stage.
        if ($funnel['daysSinceConversion'] === null || $funnel['daysSinceConversion'] > self::NEW_SITE_ZERO_CONVERSION_TOLERANCE_DAYS) {
            if ($funnel['addToCartRate'] !== null && $funnel['addToCartRate'] < 2 && $flow['yesterdayUv'] >= 20) {
                $actions[] = sprintf('🔴 [轉換] 加購率 %.1f%% 遠低於業界 5-10%% — 問題在商品頁 CTA', $funnel['addToCartRate']);
            } elseif ($funnel['addToCartSessions'] !== null && $funnel['addToCartSessions'] > 0 && $funnel['beginCheckoutSessions'] === 0) {
                $actions[] = sprintf('🔴 [轉換] %d session 加購卻 0 進結帳 — 檢查購物車 / 運費', $funnel['addToCartSessions']);
            } else {
                $actions[] = '🔴 [轉換] 親自走一次 mobile 結帳流程，錄影找斷點';
            }
            $actions[] = '🔴 [轉換] 檢查 GA4 add_to_cart / begin_checkout 事件是否有進';
        } elseif ($flow['yesterdayUv'] >= 20 && $funnel['ordersYesterday'] === 0) {
            $actions[] = sprintf('🟡 [轉換] 昨日 %d UV 但 0 進結帳 — 落地頁 CTA 檢查', $flow['yesterdayUv']);
        }

        // Priority 2: Ads efficiency
        if (($ads['configured'] ?? false) && ! empty($ads['waste'])) {
            $topWaste = $ads['waste'][0] ?? null;
            if ($topWaste) {
                $actions[] = sprintf(
                    '[Ads] `%s` 燒 NT$%s 0 轉換 → 加否定或改精準配對',
                    mb_strimwidth($topWaste['term'], 0, 24, '…'),
                    number_format($topWaste['spend']),
                );
            }
        }

        // Priority 3: SEO wins to reinforce
        if ($flow['organicWowPct'] !== null && $flow['organicWowPct'] >= 20) {
            $actions[] = sprintf('[SEO] 自然流量週增 +%d%% — 持續寫部落格文章鞏固', $flow['organicWowPct']);
        }

        // Priority 4: GEO opportunity
        if ($geo['total'] > 100 && $flow['yesterdayAi'] === 0) {
            $actions[] = sprintf('[GEO] AI 爬 %d hits 但 0 引薦 UV — 文章補 FAQ schema 提高引用率', $geo['total']);
        }

        if (! $actions) {
            return ['name' => '📌 今日行動建議', 'value' => '🟢 各管線運作正常，保持現行節奏'];
        }

        // Prefix each with a circled-number so the bot post reads like a
        // checklist. We cap at 4 actions — longer lists stop being actionable.
        $bullets = ['①', '②', '③', '④'];
        $lines = [];
        foreach (array_slice($actions, 0, 4) as $i => $text) {
            $lines[] = "{$bullets[$i]} {$text}";
        }
        return ['name' => '📌 今日行動建議', 'value' => implode("\n", $lines)];
    }

    private function pct(?int $pct): string
    {
        if ($pct === null) return '—';
        $arrow = $pct > 5 ? '▲' : ($pct < -5 ? '▼' : '━');
        $sign = $pct >= 0 ? '+' : '';
        return "{$arrow}{$sign}{$pct}%";
    }
}
