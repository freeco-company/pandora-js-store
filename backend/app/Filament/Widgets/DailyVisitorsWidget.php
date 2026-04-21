<?php

namespace App\Filament\Widgets;

use Carbon\Carbon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Illuminate\Support\Facades\DB;

/**
 * Daily visitors widget. Defaults to today's unique-visitor count, reading
 * from the `visits` raw event log. Honors the dashboard filter's endDate
 * (or startDate-endDate range) so you can scrub back to see any day.
 * Clicking the stat deep-links to the admin visit list pre-scoped to today.
 */
class DailyVisitorsWidget extends StatsOverviewWidget
{
    use InteractsWithPageFilters;

    protected static ?int $sort = 2; // after revenue/orders, before charts

    protected function getStats(): array
    {
        // Default view = today; if filter range is set, use endDate as the
        // "focus day" (since the user is typically exploring a recent date).
        $focus = $this->focusDate();
        $dayStart = $focus->copy()->startOfDay();
        $dayEnd = $focus->copy()->endOfDay();

        $uniqueVisitors = (int) DB::table('visits')
            ->whereBetween('visited_at', [$dayStart, $dayEnd])
            ->distinct('visitor_id')
            ->count('visitor_id');

        $totalHits = (int) DB::table('visits')
            ->whereBetween('visited_at', [$dayStart, $dayEnd])
            ->count();

        // Previous day for delta %
        $prevStart = $focus->copy()->subDay()->startOfDay();
        $prevEnd = $focus->copy()->subDay()->endOfDay();
        $prevVisitors = (int) DB::table('visits')
            ->whereBetween('visited_at', [$prevStart, $prevEnd])
            ->distinct('visitor_id')
            ->count('visitor_id');

        // Suppress % delta when the comparison base is tiny — first days of
        // tracking produce absurd +986% numbers that say nothing about growth.
        $delta = null;
        if ($prevVisitors >= 10) {
            $pct = round((($uniqueVisitors - $prevVisitors) / $prevVisitors) * 100);
            $delta = ($pct >= 0 ? '+' : '') . $pct . '% vs 前一日';
        } elseif ($prevVisitors > 0) {
            $diff = $uniqueVisitors - $prevVisitors;
            $sign = $diff >= 0 ? '+' : '';
            $delta = "{$sign}{$diff} vs 前一日 ({$prevVisitors})";
        }

        $focusLabel = $focus->isToday() ? '今日' : $focus->format('m/d');

        $listUrl = \App\Filament\Resources\VisitResource::getUrl('index');

        return [
            Stat::make("{$focusLabel}不重複訪客", number_format($uniqueVisitors))
                ->description($delta ?? "總瀏覽 {$totalHits} 次 · 點擊看明細")
                ->descriptionIcon($uniqueVisitors >= $prevVisitors ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down')
                ->color($uniqueVisitors >= $prevVisitors ? 'success' : 'warning')
                ->url($listUrl),

            Stat::make("{$focusLabel}總瀏覽次數", number_format($totalHits))
                ->description('每位訪客可能瀏覽多頁 · 點擊看明細')
                ->color('info')
                ->url($listUrl),
        ];
    }

    private function focusDate(): Carbon
    {
        $end = $this->pageFilters['endDate'] ?? null;
        return $end ? Carbon::parse($end) : Carbon::today();
    }
}
