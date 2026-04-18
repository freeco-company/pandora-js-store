<?php

namespace App\Filament\Widgets;

use App\Models\Order;
use Carbon\Carbon;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;

class PaymentMethodChart extends ChartWidget
{
    use InteractsWithPageFilters;

    protected ?string $heading = '付款方式分布';

    protected static ?int $sort = 3;

    protected ?string $maxHeight = '280px';

    protected function getData(): array
    {
        [$start, $end] = $this->resolveRange();

        $rows = Order::selectRaw('payment_method, COUNT(*) as cnt, COALESCE(SUM(total), 0) as revenue')
            ->whereBetween('created_at', [$start, $end])
            ->where('payment_status', 'paid')
            ->groupBy('payment_method')
            ->get()
            ->keyBy('payment_method');

        $labels = [];
        $data = [];
        $colors = [];

        $map = [
            'ecpay_credit' => ['信用卡', '#9F6B3E'],
            'bank_transfer' => ['ATM 轉帳', '#4A9D5F'],
            'cod' => ['貨到付款', '#D97706'],
        ];

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
