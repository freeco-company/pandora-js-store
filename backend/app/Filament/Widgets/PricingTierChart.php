<?php

namespace App\Filament\Widgets;

use App\Models\Order;
use Carbon\Carbon;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;

class PricingTierChart extends ChartWidget
{
    use InteractsWithPageFilters;

    protected ?string $heading = '三階梯價格分布';

    protected static ?int $sort = 6;

    protected ?string $maxHeight = '280px';

    protected function getData(): array
    {
        [$start, $end] = $this->resolveRange();

        $rows = Order::selectRaw('pricing_tier, COUNT(*) as cnt, COALESCE(SUM(total),0) as revenue')
            ->whereBetween('created_at', [$start, $end])
            ->where('payment_status', 'paid')
            ->groupBy('pricing_tier')
            ->get()
            ->keyBy('pricing_tier');

        $map = [
            'regular' => ['原價', '#6B7280'],
            'combo' => ['1+1 搭配價', '#9F6B3E'],
            'vip' => ['VIP 優惠價', '#B45309'],
        ];

        $labels = [];
        $orders = [];
        $revenue = [];
        $colors = [];

        foreach ($map as $key => [$label, $color]) {
            $labels[] = $label;
            $orders[] = isset($rows[$key]) ? (int) $rows[$key]->cnt : 0;
            $revenue[] = isset($rows[$key]) ? (int) $rows[$key]->revenue : 0;
            $colors[] = $color;
        }

        return [
            'datasets' => [
                [
                    'label' => '訂單數',
                    'data' => $orders,
                    'backgroundColor' => $colors,
                    'yAxisID' => 'y',
                    'borderRadius' => 4,
                ],
                [
                    'label' => '營收 (NT$)',
                    'data' => $revenue,
                    'type' => 'line',
                    'borderColor' => '#111827',
                    'backgroundColor' => '#111827',
                    'pointRadius' => 5,
                    'yAxisID' => 'y1',
                    'tension' => 0,
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getOptions(): array
    {
        return [
            'maintainAspectRatio' => false,
            'scales' => [
                'y' => ['beginAtZero' => true, 'title' => ['display' => true, 'text' => '訂單']],
                'y1' => [
                    'position' => 'right',
                    'beginAtZero' => true,
                    'title' => ['display' => true, 'text' => '營收'],
                    'grid' => ['drawOnChartArea' => false],
                ],
            ],
            'plugins' => [
                'legend' => ['position' => 'bottom'],
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
