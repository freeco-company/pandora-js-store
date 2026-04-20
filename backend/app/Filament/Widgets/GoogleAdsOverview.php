<?php

namespace App\Filament\Widgets;

use App\Services\GoogleAdsService;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Cache;

/**
 * Google Ads headline metrics for the date range selected on the
 * Dashboard. Caches API responses for 15 minutes so flipping between
 * presets doesn't hammer Google's quota.
 *
 * Hidden entirely if Google Ads API is not configured in .env — this
 * keeps the dashboard clean on dev/staging instances.
 */
class GoogleAdsOverview extends BaseWidget
{
    use InteractsWithPageFilters;

    protected static ?int $sort = 6;

    protected int | string | array $columnSpan = 'full';

    protected function getColumns(): int | array
    {
        return [
            'default' => 1,
            'sm' => 2,
            'xl' => 4,
        ];
    }

    public static function canView(): bool
    {
        return GoogleAdsService::fromConfig()->isConfigured();
    }

    protected function getStats(): array
    {
        [$start, $end] = $this->resolveRange();

        $cacheKey = "google_ads:metrics:{$start}:{$end}";
        $m = Cache::remember($cacheKey, now()->addMinutes(15), function () use ($start, $end) {
            return GoogleAdsService::fromConfig()->getMetricsForRange($start, $end);
        });

        $spend  = 'NT$' . number_format($m['spend']);
        $clicks = number_format($m['clicks']);
        $convs  = (string) $m['conversions'];
        $ctr    = "{$m['ctr']}%";
        $cpc    = $m['cpc'] > 0 ? 'NT$' . number_format($m['cpc']) : '—';
        $cpa    = $m['cpa'] > 0 ? 'NT$' . number_format($m['cpa']) : '—';
        $roas   = $m['roas'] > 0 ? "{$m['roas']}x" : '—';
        $convValue = 'NT$' . number_format($m['conversion_value']);

        // ROAS colour + label. Special-case 0 conversions: we can't tell
        // apart "ads are losing money" from "conversion tracking isn't wired
        // up yet", so show a neutral warning instead of red 虧錢中.
        $roasColor = 'gray';
        $roasDesc = '尚無花費';
        if ($m['spend'] > 0) {
            if ($m['conversions'] == 0) {
                $roasColor = 'warning';
                $roasDesc = '⚠️ 0 轉換（請確認 GTM 轉換追蹤）';
            } elseif ($m['roas'] >= 2) {
                $roasColor = 'success';
                $roasDesc = '表現不錯';
            } elseif ($m['roas'] >= 1) {
                $roasColor = 'warning';
                $roasDesc = '勉強回本';
            } else {
                $roasColor = 'danger';
                $roasDesc = '⚠️ 虧錢中';
            }
        }

        return [
            Stat::make('Google Ads 花費', $spend)
                ->description("{$clicks} 次點擊 · CTR {$ctr}")
                ->descriptionIcon('heroicon-m-currency-dollar')
                ->color('primary')
                ->chart($this->miniChart('spend', 14)),

            Stat::make('轉換數', $convs)
                ->description("轉換金額 {$convValue}")
                ->descriptionIcon('heroicon-m-check-badge')
                ->color($m['conversions'] > 0 ? 'success' : 'gray')
                ->chart($this->miniChart('conversions', 14)),

            Stat::make('CPC / CPA', "{$cpc} / {$cpa}")
                ->description('每點擊 / 每轉換成本')
                ->descriptionIcon('heroicon-m-calculator')
                ->color('info'),

            Stat::make('ROAS', $roas)
                ->description($roasDesc)
                ->descriptionIcon($m['roas'] >= 1 ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down')
                ->color($roasColor),
        ];
    }

    /**
     * Mini sparkline for the last N days of a given metric.
     * Cached per-metric for 15 min.
     *
     * @return list<int|float>
     */
    private function miniChart(string $metric, int $days): array
    {
        $cacheKey = "google_ads:series:{$days}";
        $series = Cache::remember($cacheKey, now()->addMinutes(15), function () use ($days) {
            return GoogleAdsService::fromConfig()->getDailySeries($days);
        });

        if (empty($series)) {
            return array_fill(0, $days, 0);
        }

        return array_map(fn ($row) => $row[$metric] ?? 0, $series);
    }

    /**
     * Resolve the Filament Dashboard date filter (same format as other widgets).
     */
    private function resolveRange(): array
    {
        $start = $this->pageFilters['startDate'] ?? null;
        $end   = $this->pageFilters['endDate'] ?? null;

        $start = $start
            ? \Carbon\Carbon::parse($start)->toDateString()
            : now()->subDays(29)->toDateString();
        $end = $end
            ? \Carbon\Carbon::parse($end)->toDateString()
            : now()->toDateString();

        return [$start, $end];
    }
}
