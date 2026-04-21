<?php

namespace App\Filament\Widgets;

use App\Models\Visit;
use Carbon\Carbon;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Traffic stats — used both on the /admin (dashboard) page and as the
 * VisitResource list page's header widget.
 *
 * Splits UV into: 自然 (organic search + direct + referral) vs 廣告
 * (Google Ads + any paid utm). The line between the two is the clearest
 * signal of whether organic SEO work is paying off vs whether we're
 * only renting traffic.
 *
 * Responds to the dashboard date filter via InteractsWithPageFilters so
 * "今日 / 最近 7 天 / 本月" all work without editing this widget.
 */
class VisitStatsWidget extends StatsOverviewWidget
{
    use InteractsWithPageFilters;

    protected static ?int $sort = 1;

    protected int | string | array $columnSpan = 'full';

    protected function getColumns(): int
    {
        return 4;
    }

    protected function getStats(): array
    {
        [$start, $end] = $this->resolveRange();

        $baseQuery = fn () => Visit::whereBetween('visited_at', [$start, $end]);

        $uv = (clone $baseQuery())->distinct('visitor_id')->count('visitor_id');
        $pv = (clone $baseQuery())->count();

        // Paid = referer_source google_ads OR utm_medium cpc/paid/ads.
        // Organic = everything else (direct + google organic + social + referral).
        $paidUv = (clone $baseQuery())
            ->where(function ($q) {
                $q->where('referer_source', 'google_ads')
                    ->orWhereIn('utm_medium', ['cpc', 'paid', 'ads', 'ppc']);
            })
            ->distinct('visitor_id')
            ->count('visitor_id');
        $organicUv = max(0, $uv - $paidUv);

        $members = (clone $baseQuery())
            ->whereNotNull('customer_id')
            ->distinct('visitor_id')
            ->count('visitor_id');

        // Compare to previous equal-length window so delta % is meaningful
        // regardless of whether user picked "today" or "last 30 days".
        $durationDays = max(1, $start->diffInDays($end) + 1);
        $prevStart = (clone $start)->subDays($durationDays);
        $prevEnd = (clone $start)->subDay()->endOfDay();
        $prevUv = Visit::whereBetween('visited_at', [$prevStart, $prevEnd])
            ->distinct('visitor_id')
            ->count('visitor_id');
        $delta = $this->deltaLabel($uv, $prevUv);

        $listUrl = \App\Filament\Resources\VisitResource::getUrl('index');
        $label = $this->rangeLabel($start, $end);
        $pvPerUv = $uv > 0 ? round($pv / $uv, 1) : 0;

        return [
            Stat::make("{$label}不重複訪客", number_format($uv))
                ->description($delta ?: "瀏覽 {$pv} 頁 · 人均 {$pvPerUv}")
                ->descriptionIcon('heroicon-m-users')
                ->color('primary')
                ->url($listUrl),

            Stat::make('自然流量', number_format($organicUv))
                ->description($uv > 0 ? round($organicUv / $uv * 100) . '% · SEO + 直接 + 社群' : '—')
                ->descriptionIcon('heroicon-m-sparkles')
                ->color('success'),

            Stat::make('廣告流量', number_format($paidUv))
                ->description($uv > 0 ? round($paidUv / $uv * 100) . '% · Google Ads / 付費' : '—')
                ->descriptionIcon('heroicon-m-megaphone')
                ->color($paidUv > 0 ? 'warning' : 'gray'),

            Stat::make('會員訪客', number_format($members))
                ->description($uv > 0 ? round($members / $uv * 100) . '% 已登入' : '—')
                ->descriptionIcon('heroicon-m-user-circle')
                ->color($members > 0 ? 'info' : 'gray'),
        ];
    }

    /** @return array{0: Carbon, 1: Carbon} */
    private function resolveRange(): array
    {
        $startFilter = $this->pageFilters['startDate'] ?? null;
        $endFilter = $this->pageFilters['endDate'] ?? null;

        if ($startFilter && $endFilter) {
            return [
                Carbon::parse($startFilter)->startOfDay(),
                Carbon::parse($endFilter)->endOfDay(),
            ];
        }
        // Default = today only (matches list page's "僅今日" default filter)
        return [today()->startOfDay(), today()->endOfDay()];
    }

    private function rangeLabel(Carbon $start, Carbon $end): string
    {
        if ($start->isSameDay($end)) return $start->isToday() ? '今日' : $start->format('m/d');
        if ($start->isToday()) return '今日';
        $days = $start->diffInDays($end) + 1;
        return "{$days} 天";
    }

    private function deltaLabel(int $current, int $prev): ?string
    {
        if ($prev < 5) return null; // too small to compare meaningfully
        $pct = round((($current - $prev) / $prev) * 100);
        $sign = $pct >= 0 ? '+' : '';
        return "{$sign}{$pct}% vs 前期（{$prev}）";
    }
}
