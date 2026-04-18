<?php

namespace App\Filament\Widgets;

use App\Models\Order;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Carbon;

class RevenueChart extends ChartWidget
{
    protected ?string $heading = '近 14 日營收趨勢';

    protected static ?int $sort = 3;

    protected ?string $maxHeight = '280px';

    protected ?string $pollingInterval = '300s';

    protected function getData(): array
    {
        $days = 14;
        $labels = [];
        $revenue = [];
        $orders = [];

        for ($i = $days - 1; $i >= 0; $i--) {
            $date = Carbon::today()->subDays($i);
            $labels[] = $date->format('m/d');

            $dayOrders = Order::whereDate('created_at', $date)
                ->where('payment_status', 'paid');

            $revenue[] = (int) $dayOrders->sum('total');
            $orders[] = (clone $dayOrders)->count();
        }

        return [
            'datasets' => [
                [
                    'label' => '營收 (NT$)',
                    'data' => $revenue,
                    'backgroundColor' => 'rgba(159, 107, 62, 0.1)',
                    'borderColor' => '#9F6B3E',
                    'pointBackgroundColor' => '#9F6B3E',
                    'fill' => true,
                    'tension' => 0.3,
                    'yAxisID' => 'y',
                ],
                [
                    'label' => '訂單數',
                    'data' => $orders,
                    'backgroundColor' => 'rgba(74, 157, 95, 0.1)',
                    'borderColor' => '#4A9D5F',
                    'pointBackgroundColor' => '#4A9D5F',
                    'fill' => false,
                    'tension' => 0.3,
                    'yAxisID' => 'y1',
                ],
            ],
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
            'scales' => [
                'y' => [
                    'position' => 'left',
                    'beginAtZero' => true,
                    'ticks' => [
                        'callback' => "function(value) { return 'NT$' + value.toLocaleString(); }",
                    ],
                ],
                'y1' => [
                    'position' => 'right',
                    'beginAtZero' => true,
                    'grid' => ['drawOnChartArea' => false],
                ],
            ],
            'plugins' => [
                'legend' => ['position' => 'bottom'],
            ],
        ];
    }
}
