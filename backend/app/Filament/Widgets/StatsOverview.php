<?php

namespace App\Filament\Widgets;

use App\Models\Blacklist;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use Carbon\Carbon;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;

class StatsOverview extends BaseWidget
{
    use InteractsWithPageFilters;

    protected static ?int $sort = 1;

    protected ?string $pollingInterval = '120s';

    protected int | string | array $columnSpan = 'full';

    protected function getColumns(): int | array
    {
        return [
            'default' => 1,
            'sm' => 2,
            'xl' => 4,
        ];
    }

    protected function getStats(): array
    {
        [$start, $end] = $this->resolveRange();

        $paidInRange = Order::whereBetween('created_at', [$start, $end])
            ->where('payment_status', 'paid');

        $revenue = (int) (clone $paidInRange)->sum('total');
        $paidOrderCount = (clone $paidInRange)->count();
        $totalOrders = Order::whereBetween('created_at', [$start, $end])->count();
        $aov = $paidOrderCount > 0 ? (int) round($revenue / $paidOrderCount) : 0;
        $unitsSold = (int) OrderItem::whereHas('order', fn ($q) => $q
            ->whereBetween('created_at', [$start, $end])
            ->where('payment_status', 'paid'))->sum('quantity');

        // Period-over-period comparison (same-length prior window)
        $periodDays = max(1, $start->diffInDays($end) + 1);
        $prevStart = (clone $start)->subDays($periodDays);
        $prevEnd = (clone $start)->subSecond();
        $prevRevenue = (int) Order::whereBetween('created_at', [$prevStart, $prevEnd])
            ->where('payment_status', 'paid')->sum('total');
        $prevOrders = Order::whereBetween('created_at', [$prevStart, $prevEnd])
            ->where('payment_status', 'paid')->count();
        $revenueDelta = $this->deltaPct($revenue, $prevRevenue);
        $orderDelta = $this->deltaPct($paidOrderCount, $prevOrders);

        $paymentRate = $totalOrders > 0
            ? round(($paidOrderCount / $totalOrders) * 100, 1)
            : 0;

        $newCustomers = Customer::whereBetween('created_at', [$start, $end])->count();
        $activeCustomers = (int) (clone $paidInRange)->distinct('customer_id')->count('customer_id');
        $repeatCustomers = (int) DB::table('orders')
            ->select('customer_id')
            ->whereBetween('created_at', [$start, $end])
            ->where('payment_status', 'paid')
            ->whereNotNull('customer_id')
            ->groupBy('customer_id')
            ->havingRaw('COUNT(*) > 1')
            ->get()->count();
        $repeatRate = $activeCustomers > 0
            ? round(($repeatCustomers / $activeCustomers) * 100, 1)
            : 0;

        // Real-time actionables (not date-filtered)
        $pendingPayment = Order::where('payment_status', 'unpaid')
            ->whereNotIn('status', ['cancelled', 'refunded'])->count();
        $pendingShipment = Order::where('payment_status', 'paid')
            ->where('status', 'processing')->count();
        $cvsNeedsLogistics = Order::whereIn('shipping_method', ['cvs_711', 'cvs_family'])
            ->where('payment_status', 'paid')
            ->whereNull('ecpay_logistics_id')->count();

        return [
            Stat::make('期間營收', 'NT$' . number_format($revenue))
                ->description($this->deltaLabel($revenueDelta, '與前一等長期間比'))
                ->descriptionIcon($revenueDelta !== null && $revenueDelta < 0
                    ? 'heroicon-m-arrow-trending-down'
                    : 'heroicon-m-arrow-trending-up')
                ->color($revenueDelta !== null && $revenueDelta < 0 ? 'danger' : 'success')
                ->chart($this->miniChart($start, $end, 'revenue')),

            Stat::make('期間訂單', number_format($paidOrderCount))
                ->description($this->deltaLabel($orderDelta, '已付款訂單'))
                ->descriptionIcon($orderDelta !== null && $orderDelta < 0
                    ? 'heroicon-m-arrow-trending-down'
                    : 'heroicon-m-arrow-trending-up')
                ->color($orderDelta !== null && $orderDelta < 0 ? 'danger' : 'primary')
                ->chart($this->miniChart($start, $end, 'orders')),

            Stat::make('平均客單價', 'NT$' . number_format($aov))
                ->description("已付款訂單平均 · 共 {$unitsSold} 件")
                ->descriptionIcon('heroicon-m-calculator')
                ->color('info'),

            Stat::make('付款轉換率', $paymentRate . '%')
                ->description("總訂單 {$totalOrders} / 付款 {$paidOrderCount}")
                ->descriptionIcon('heroicon-m-credit-card')
                ->color($paymentRate >= 70 ? 'success' : ($paymentRate >= 50 ? 'warning' : 'danger')),

            Stat::make('新會員', number_format($newCustomers))
                ->description('期間內註冊')
                ->descriptionIcon('heroicon-m-user-plus')
                ->color('primary'),

            Stat::make('活躍買家', number_format($activeCustomers))
                ->description("回購率 {$repeatRate}%（≥2 單）")
                ->descriptionIcon('heroicon-m-users')
                ->color('info'),

            Stat::make('待付款 ⚡', $pendingPayment)
                ->description('未完成付款（即時）')
                ->descriptionIcon('heroicon-m-clock')
                ->color($pendingPayment > 5 ? 'warning' : 'gray')
                ->url($pendingPayment > 0
                    ? \App\Filament\Resources\OrderResource::getUrl('index', ['tableFilters[payment_status][value]' => 'unpaid'])
                    : null),

            Stat::make('待出貨 / 待建物流', "{$pendingShipment} / {$cvsNeedsLogistics}")
                ->description('處理中 + CVS 未建單（即時）')
                ->descriptionIcon('heroicon-m-truck')
                ->color($cvsNeedsLogistics > 0 ? 'danger' : ($pendingShipment > 0 ? 'warning' : 'gray'))
                ->url($cvsNeedsLogistics > 0
                    ? \App\Filament\Resources\OrderResource::getUrl('index', ['tableFilters[needs_logistics][isActive]' => true])
                    : ($pendingShipment > 0
                        ? \App\Filament\Resources\OrderResource::getUrl('index', ['tableFilters[status][value]' => 'processing'])
                        : null)),
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

    protected function deltaPct(int $current, int $previous): ?int
    {
        if ($previous <= 0) {
            return null;
        }
        return (int) round((($current - $previous) / $previous) * 100);
    }

    protected function deltaLabel(?int $pct, string $fallback): string
    {
        if ($pct === null) {
            return $fallback;
        }
        return $pct >= 0 ? "+{$pct}%（{$fallback}）" : "{$pct}%（{$fallback}）";
    }

    protected function miniChart(Carbon $start, Carbon $end, string $metric): array
    {
        $days = min(14, max(1, $start->diffInDays($end) + 1));
        $cursor = (clone $end)->startOfDay()->subDays($days - 1);
        $out = [];
        for ($i = 0; $i < $days; $i++) {
            $day = (clone $cursor)->addDays($i);
            $q = Order::whereDate('created_at', $day)->where('payment_status', 'paid');
            $out[] = $metric === 'revenue' ? (int) $q->sum('total') : (int) $q->count();
        }
        return $out;
    }
}
