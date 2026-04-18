<?php

namespace App\Filament\Widgets;

use App\Models\Order;
use Carbon\Carbon;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;

/**
 * Order-stage distribution pie chart — where are orders right now?
 * (待處理 / 處理中 / 已出貨 / 已完成 / 已取消 / 已退款 / 未取件)
 *
 * NOTE: keeping the class name PricingTierChart for filesystem stability —
 * the file was repurposed from a 3-tier pricing bar chart to this pie.
 */
class PricingTierChart extends ChartWidget
{
    use InteractsWithPageFilters;

    protected ?string $heading = '訂單階段分布';

    protected static ?int $sort = 6;

    protected ?string $maxHeight = '320px';

    /**
     * Status → [label, color]. Color palette picked to roughly match the
     * urgency gradient: orange/brown = action needed, green = done,
     * gray/red = terminal states.
     */
    private const STATUS_MAP = [
        'pending'       => ['待處理',   '#E8A93B'],
        'processing'    => ['處理中',   '#9F6B3E'],
        'shipped'       => ['已出貨',   '#4A9ECD'],
        'completed'     => ['已完成',   '#4A9D5F'],
        'cancelled'     => ['已取消',   '#9CA3AF'],
        'refunded'      => ['已退款',   '#D4762C'],
        'cod_no_pickup' => ['未取件',   '#C0392B'],
    ];

    protected function getData(): array
    {
        [$start, $end] = $this->resolveRange();

        $rows = Order::selectRaw('status, COUNT(*) as cnt')
            ->whereBetween('created_at', [$start, $end])
            ->groupBy('status')
            ->pluck('cnt', 'status');

        $labels = [];
        $data = [];
        $colors = [];

        foreach (self::STATUS_MAP as $status => [$label, $color]) {
            $count = (int) ($rows[$status] ?? 0);
            if ($count === 0) continue; // skip empty slices so the pie stays readable
            $labels[] = "{$label} ({$count})";
            $data[] = $count;
            $colors[] = $color;
        }

        // Fallback when nothing in range — keep the chart from rendering empty
        if (empty($data)) {
            $labels = ['本期尚無訂單'];
            $data = [1];
            $colors = ['#E5E7EB'];
        }

        return [
            'datasets' => [
                [
                    'data' => $data,
                    'backgroundColor' => $colors,
                    'borderWidth' => 2,
                    'borderColor' => '#ffffff',
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }

    protected function getOptions(): array
    {
        return [
            'maintainAspectRatio' => false,
            'plugins' => [
                'legend' => ['position' => 'right'],
            ],
        ];
    }

    protected function resolveRange(): array
    {
        $start = $this->pageFilters['startDate'] ?? null;
        $end = $this->pageFilters['endDate'] ?? null;

        $start = $start ? Carbon::parse($start)->startOfDay() : now()->subDays(29)->startOfDay();
        $end = $end ? Carbon::parse($end)->endOfDay() : now()->endOfDay();

        return [$start, $end];
    }
}
