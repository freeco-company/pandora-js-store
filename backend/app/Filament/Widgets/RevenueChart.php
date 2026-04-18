<?php

namespace App\Filament\Widgets;

use App\Models\Order;
use Carbon\Carbon;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;

class RevenueChart extends ChartWidget
{
    use InteractsWithPageFilters;

    protected ?string $heading = '期間營收 × 訂單趨勢';

    protected static ?int $sort = 2;

    protected ?string $maxHeight = '320px';

    protected int | string | array $columnSpan = 'full';

    protected function getData(): array
    {
        [$start, $end] = $this->resolveRange();
        $totalDays = $start->diffInDays($end) + 1;

        // If range > 62 days, bucket by week; > 365 days, by month
        $bucket = $totalDays > 365 ? 'month' : ($totalDays > 62 ? 'week' : 'day');

        $labels = [];
        $revenue = [];
        $orders = [];

        if ($bucket === 'day') {
            $cursor = (clone $start);
            while ($cursor <= $end) {
                $labels[] = $cursor->format('m/d');
                $dayOrders = Order::whereDate('created_at', $cursor)
                    ->where('payment_status', 'paid');
                $revenue[] = (int) $dayOrders->sum('total');
                $orders[] = (clone $dayOrders)->count();
                $cursor->addDay();
            }
        } elseif ($bucket === 'week') {
            $cursor = (clone $start)->startOfWeek();
            while ($cursor <= $end) {
                $weekEnd = (clone $cursor)->endOfWeek();
                $labels[] = $cursor->format('m/d');
                $q = Order::whereBetween('created_at', [
                    max($cursor, $start),
                    min($weekEnd, $end),
                ])->where('payment_status', 'paid');
                $revenue[] = (int) $q->sum('total');
                $orders[] = (clone $q)->count();
                $cursor->addWeek();
            }
        } else {
            $cursor = (clone $start)->startOfMonth();
            while ($cursor <= $end) {
                $monthEnd = (clone $cursor)->endOfMonth();
                $labels[] = $cursor->format('Y/m');
                $q = Order::whereBetween('created_at', [
                    max($cursor, $start),
                    min($monthEnd, $end),
                ])->where('payment_status', 'paid');
                $revenue[] = (int) $q->sum('total');
                $orders[] = (clone $q)->count();
                $cursor->addMonthNoOverflow();
            }
        }

        return [
            'datasets' => [
                [
                    'label' => '營收 (NT$)',
                    'data' => $revenue,
                    'backgroundColor' => 'rgba(159, 107, 62, 0.15)',
                    'borderColor' => '#9F6B3E',
                    'pointBackgroundColor' => '#9F6B3E',
                    'fill' => true,
                    'tension' => 0.35,
                    'yAxisID' => 'y',
                ],
                [
                    'label' => '訂單數',
                    'data' => $orders,
                    'backgroundColor' => 'rgba(74, 157, 95, 0.1)',
                    'borderColor' => '#4A9D5F',
                    'pointBackgroundColor' => '#4A9D5F',
                    'fill' => false,
                    'tension' => 0.35,
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
            'maintainAspectRatio' => false,
            'interaction' => ['mode' => 'index', 'intersect' => false],
            'scales' => [
                'y' => [
                    'position' => 'left',
                    'beginAtZero' => true,
                    'title' => ['display' => true, 'text' => '營收'],
                ],
                'y1' => [
                    'position' => 'right',
                    'beginAtZero' => true,
                    'title' => ['display' => true, 'text' => '訂單'],
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
