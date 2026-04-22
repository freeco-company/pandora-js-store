<?php

namespace App\Filament\Widgets;

use App\Models\Visit;
use Carbon\Carbon;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Illuminate\Support\Facades\DB;

/**
 * Daily unique-visitor trend, split into 自然 (organic + direct + social)
 * vs 廣告 (paid). Lets the admin see day-over-day whether SEO work is
 * compounding or whether they're dependent on ads spend.
 *
 * Responds to dashboard page filter (StartDate/EndDate). Defaults to the
 * last 30 days when no filter is set — a single-day range collapses the
 * chart to two points which isn't useful, so we widen in that case.
 */
class VisitTrendChart extends ChartWidget
{
    use InteractsWithPageFilters;

    protected ?string $heading = '每日流量趨勢（自然 vs 廣告）';

    protected static ?int $sort = 3;

    protected ?string $maxHeight = '300px';

    protected int | string | array $columnSpan = 'full';

    protected function getType(): string
    {
        return 'line';
    }

    /** Source buckets counted as paid. Keep in sync with VisitController::normalizeSource. */
    private const PAID_SOURCES = ['google_ads', 'facebook_ads', 'bing_ads', 'tiktok_ads', 'linkedin_ads', 'other_ads'];

    protected function getData(): array
    {
        [$start, $end] = $this->resolveRange();

        // Single day → widen to last 14 so the line isn't a single dot. We
        // keep the end date so user's "today" focus still shows, but we
        // pull context.
        if ($start->isSameDay($end)) {
            $start = (clone $end)->subDays(13)->startOfDay();
        }

        // Paid UV per day. Separate query from total UV because COUNT DISTINCT
        // can't be split by a CASE inside one aggregation.
        $paidUvPerDay = Visit::selectRaw('DATE(visited_at) as d, COUNT(DISTINCT visitor_id) as uv')
            ->whereBetween('visited_at', [$start, $end])
            ->where('referer_source', '!=', 'bot')
            ->where(function ($q) {
                $q->whereIn('referer_source', self::PAID_SOURCES)
                    ->orWhereIn('utm_medium', ['cpc', 'paid', 'ads', 'ppc']);
            })
            ->groupByRaw('DATE(visited_at)')
            ->pluck('uv', 'd')
            ->toArray();

        $totalUvPerDay = Visit::selectRaw('DATE(visited_at) as d, COUNT(DISTINCT visitor_id) as uv')
            ->whereBetween('visited_at', [$start, $end])
            ->where('referer_source', '!=', 'bot')
            ->groupByRaw('DATE(visited_at)')
            ->pluck('uv', 'd')
            ->toArray();

        // Walk every day in range so zero-traffic days show as 0 instead of
        // a gap — a missing line point is visually confusing ("did it crash?").
        $labels = [];
        $organic = [];
        $paid = [];
        $cursor = $start->copy();
        $endDate = $end->copy()->endOfDay();
        while ($cursor <= $endDate) {
            $d = $cursor->toDateString();
            $labels[] = $cursor->format('m/d');
            $totalUv = (int) ($totalUvPerDay[$d] ?? 0);
            $paidUv = (int) ($paidUvPerDay[$d] ?? 0);
            $organic[] = max(0, $totalUv - $paidUv);
            $paid[] = $paidUv;
            $cursor->addDay();
        }

        return [
            'datasets' => [
                [
                    'label' => '自然流量',
                    'data' => $organic,
                    'borderColor' => '#10B981',
                    'backgroundColor' => 'rgba(16, 185, 129, 0.1)',
                    'tension' => 0.3,
                    'fill' => true,
                ],
                [
                    'label' => '廣告流量',
                    'data' => $paid,
                    'borderColor' => '#F59E0B',
                    'backgroundColor' => 'rgba(245, 158, 11, 0.1)',
                    'tension' => 0.3,
                    'fill' => true,
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getOptions(): array
    {
        return [
            'plugins' => [
                'legend' => ['display' => true, 'position' => 'bottom'],
            ],
            'scales' => [
                'y' => ['beginAtZero' => true, 'ticks' => ['precision' => 0]],
            ],
            'interaction' => ['mode' => 'index', 'intersect' => false],
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
        return [today()->subDays(29)->startOfDay(), today()->endOfDay()];
    }
}
