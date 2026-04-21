<?php

namespace App\Filament\Widgets;

use App\Models\Visit;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Header widget on the Visit list page. Summarizes today's traffic so the
 * admin gets a 5-second read before scrolling the row list: how many UV,
 * how many pages per visitor, and where traffic is coming from.
 * Separate from the dashboard DailyVisitorsWidget (which covers historic
 * trend) — this one is today-only and always relative to "now".
 */
class VisitStatsWidget extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected int | string | array $columnSpan = 'full';

    protected function getColumns(): int
    {
        return 4;
    }

    protected function getStats(): array
    {
        $today = today();
        $start = $today->copy()->startOfDay();
        $end = $today->copy()->endOfDay();

        $uv = Visit::whereBetween('visited_at', [$start, $end])->distinct('visitor_id')->count('visitor_id');
        $pv = Visit::whereBetween('visited_at', [$start, $end])->count();
        $pvPerUv = $uv > 0 ? round($pv / $uv, 1) : 0;

        $members = Visit::whereBetween('visited_at', [$start, $end])
            ->whereNotNull('customer_id')
            ->distinct('visitor_id')
            ->count('visitor_id');

        // Source breakdown (UV unique per source so one browsing person
        // doesn't inflate their source into looking dominant).
        $sources = Visit::whereBetween('visited_at', [$start, $end])
            ->selectRaw('referer_source, COUNT(DISTINCT visitor_id) as uv')
            ->groupBy('referer_source')
            ->orderByDesc('uv')
            ->pluck('uv', 'referer_source')
            ->toArray();
        $topSource = array_key_first($sources) ?? '—';
        $topSourceUv = $sources[$topSource] ?? 0;

        // Device breakdown
        $deviceRows = Visit::whereBetween('visited_at', [$start, $end])
            ->selectRaw('device_type, COUNT(DISTINCT visitor_id) as uv')
            ->groupBy('device_type')
            ->pluck('uv', 'device_type')
            ->toArray();
        $mobile = ($deviceRows['mobile'] ?? 0) + ($deviceRows['tablet'] ?? 0);
        $desktop = $deviceRows['desktop'] ?? 0;

        $sourceLabel = match ($topSource) {
            'direct' => '直接進站',
            'google' => 'Google',
            'google_ads' => 'Google Ads',
            'facebook' => 'Facebook',
            'instagram' => 'Instagram',
            'line' => 'LINE',
            'bing' => 'Bing',
            'yahoo' => 'Yahoo',
            'email' => 'Email',
            'other' => '其他',
            default => '—',
        };

        return [
            Stat::make('今日不重複訪客', number_format($uv))
                ->description("瀏覽 {$pv} 頁 · 人均 {$pvPerUv} 頁")
                ->descriptionIcon('heroicon-m-users')
                ->color('primary'),

            Stat::make('會員訪客', number_format($members))
                ->description($uv > 0 ? round($members / $uv * 100) . '% 已登入' : '—')
                ->descriptionIcon('heroicon-m-user-circle')
                ->color($members > 0 ? 'success' : 'gray'),

            Stat::make('最大來源', $sourceLabel)
                ->description("{$topSourceUv} 人 · 佔 " . ($uv > 0 ? round($topSourceUv / $uv * 100) : 0) . '%')
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->color('info'),

            Stat::make('裝置分布', "{$mobile} / {$desktop}")
                ->description('手機+平板 / 桌機')
                ->descriptionIcon('heroicon-m-device-phone-mobile')
                ->color('warning'),
        ];
    }
}
