<?php

namespace App\Filament\Widgets;

use App\Models\SeoMetric;
use Filament\Widgets\ChartWidget;

/**
 * Trend chart for Core Web Vitals (LCP) on the 3 representative URL
 * templates we measure weekly. Slope matters more than absolute values
 * — sudden spikes after a deploy = something we shipped slowed pages down.
 *
 * Data source: seo_metrics_weekly, populated by `seo:weekly-snapshot`
 * (Mondays 06:00). If the table is empty the widget shows a hint
 * pointing at the env var the cron needs.
 */
class SeoMetricsChart extends ChartWidget
{
    protected ?string $heading = 'Core Web Vitals 趨勢（LCP·行動裝置）';

    protected static ?int $sort = 11;

    protected ?string $maxHeight = '300px';

    protected int | string | array $columnSpan = 'full';

    public ?string $filter = 'lcp_ms';

    private const LABEL_NAMES = [
        'home'           => '首頁',
        'product_detail' => '商品頁',
        'article_detail' => '文章頁',
    ];

    private const LABEL_COLORS = [
        'home'           => '#9F6B3E',
        'product_detail' => '#c0392b',
        'article_detail' => '#3498db',
    ];

    protected function getFilters(): ?array
    {
        return [
            'lcp_ms'     => 'LCP（毫秒，越低越好）',
            'cls_x1000'  => 'CLS（×1000，越低越好）',
            'tbt_ms'     => 'TBT（毫秒，越低越好）',
            'perf_score' => '效能分數（越高越好）',
        ];
    }

    protected function getData(): array
    {
        $rows = SeoMetric::where('strategy', 'mobile')
            ->orderBy('measured_on')
            ->get(['measured_on', 'label', $this->filter]);

        if ($rows->isEmpty()) {
            return [
                'datasets' => [],
                'labels' => ['尚未有資料 — 設定 PAGESPEED_API_KEY 並等下次 Monday 06:00 排程'],
            ];
        }

        $byDate = $rows->groupBy(fn ($r) => $r->measured_on->format('Y-m-d'));
        $dates = $byDate->keys()->sort()->values();
        $labels = $dates->map(fn ($d) => substr($d, 5))->all();

        $datasets = [];
        foreach (self::LABEL_NAMES as $slug => $label) {
            $data = $dates->map(function ($d) use ($byDate, $slug) {
                $row = $byDate[$d]->firstWhere('label', $slug);
                return $row?->{$this->filter};
            })->all();
            if (array_filter($data, fn ($v) => $v !== null) === []) continue;
            $datasets[] = [
                'label' => $label,
                'data'  => $data,
                'borderColor'     => self::LABEL_COLORS[$slug] ?? '#9CA3AF',
                'backgroundColor' => (self::LABEL_COLORS[$slug] ?? '#9CA3AF') . '20',
                'tension' => 0.25,
                'spanGaps' => true,
            ];
        }

        return [
            'datasets' => $datasets,
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }

    protected function getOptions(): array
    {
        return [
            'plugins' => [
                'legend' => ['display' => true, 'position' => 'bottom'],
            ],
            'scales' => [
                'y' => ['beginAtZero' => true],
            ],
        ];
    }
}
