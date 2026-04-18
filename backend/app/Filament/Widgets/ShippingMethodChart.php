<?php

namespace App\Filament\Widgets;

use App\Models\Order;
use Carbon\Carbon;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;

class ShippingMethodChart extends ChartWidget
{
    use InteractsWithPageFilters;

    protected ?string $heading = '配送方式分布';

    protected static ?int $sort = 4;

    protected ?string $maxHeight = '280px';

    protected function getData(): array
    {
        [$start, $end] = $this->resolveRange();

        $rows = Order::selectRaw('shipping_method, COUNT(*) as cnt')
            ->whereBetween('created_at', [$start, $end])
            ->where('payment_status', 'paid')
            ->groupBy('shipping_method')
            ->get()
            ->keyBy('shipping_method');

        $map = [
            'home_delivery' => ['宅配到府', '#2563EB'],
            'cvs_711' => ['7-11 超取', '#16A34A'],
            'cvs_family' => ['全家超取', '#DC2626'],
        ];

        $labels = [];
        $data = [];
        $colors = [];

        foreach ($map as $key => [$label, $color]) {
            if (isset($rows[$key]) && $rows[$key]->cnt > 0) {
                $labels[] = $label;
                $data[] = (int) $rows[$key]->cnt;
                $colors[] = $color;
            }
        }

        return [
            'datasets' => [[
                'label' => '訂單數',
                'data' => $data,
                'backgroundColor' => $colors,
                'borderWidth' => 0,
            ]],
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
