<?php

namespace App\Filament\Widgets;

use App\Models\Blacklist;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StatsOverview extends BaseWidget
{
    protected static ?int $sort = 1;

    // Refresh every 60s so today's counts update without reload
    protected ?string $pollingInterval = '60s';

    protected function getStats(): array
    {
        $today = now()->startOfDay();
        $thisMonth = now()->startOfMonth();
        $lastMonth = now()->subMonthNoOverflow()->startOfMonth();

        $todayOrders = Order::where('created_at', '>=', $today)->count();
        $todayRevenue = (int) Order::where('created_at', '>=', $today)
            ->where('payment_status', 'paid')->sum('total');

        $monthRevenue = (int) Order::where('created_at', '>=', $thisMonth)
            ->where('payment_status', 'paid')->sum('total');
        $lastMonthRevenue = (int) Order::whereBetween('created_at', [$lastMonth, $thisMonth])
            ->where('payment_status', 'paid')->sum('total');
        $deltaPct = $lastMonthRevenue > 0
            ? (int) round((($monthRevenue - $lastMonthRevenue) / $lastMonthRevenue) * 100)
            : null;

        $pendingPayment = Order::where('payment_status', 'unpaid')
            ->whereNotIn('status', ['cancelled', 'refunded'])->count();
        $pendingShipment = Order::where('payment_status', 'paid')
            ->where('status', 'processing')->count();
        $cvsNeedsLogistics = Order::whereIn('shipping_method', ['cvs_711', 'cvs_family'])
            ->where('payment_status', 'paid')
            ->whereNull('ecpay_logistics_id')->count();

        return [
            Stat::make('今日訂單', $todayOrders)
                ->description('今日新建立訂單')
                ->descriptionIcon('heroicon-m-shopping-bag')
                ->color($todayOrders > 0 ? 'primary' : 'gray'),

            Stat::make('今日營收', 'NT$' . number_format($todayRevenue))
                ->description('今日已付款')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('success'),

            Stat::make('本月營收', 'NT$' . number_format($monthRevenue))
                ->description($deltaPct === null
                    ? '上月無比較'
                    : ($deltaPct >= 0 ? "較上月 +{$deltaPct}%" : "較上月 {$deltaPct}%"))
                ->descriptionIcon($deltaPct !== null && $deltaPct < 0
                    ? 'heroicon-m-arrow-trending-down'
                    : 'heroicon-m-arrow-trending-up')
                ->color($deltaPct !== null && $deltaPct < 0 ? 'danger' : 'success'),

            Stat::make('待付款', $pendingPayment)
                ->description('尚未完成付款的訂單')
                ->descriptionIcon('heroicon-m-clock')
                ->color($pendingPayment > 5 ? 'warning' : 'gray')
                ->url($pendingPayment > 0
                    ? \App\Filament\Resources\OrderResource::getUrl('index', ['tableFilters[payment_status][value]' => 'unpaid'])
                    : null),

            Stat::make('待出貨', $pendingShipment)
                ->description('已付款等待出貨')
                ->descriptionIcon('heroicon-m-truck')
                ->color($pendingShipment > 0 ? 'warning' : 'gray')
                ->url($pendingShipment > 0
                    ? \App\Filament\Resources\OrderResource::getUrl('index', ['tableFilters[status][value]' => 'processing'])
                    : null),

            Stat::make('待建立物流', $cvsNeedsLogistics)
                ->description('超商取貨付款後未建單')
                ->descriptionIcon('heroicon-m-building-storefront')
                ->color($cvsNeedsLogistics > 0 ? 'danger' : 'gray')
                ->url($cvsNeedsLogistics > 0
                    ? \App\Filament\Resources\OrderResource::getUrl('index', ['tableFilters[needs_logistics][isActive]' => true])
                    : null),

            Stat::make('商品', Product::where('is_active', true)->count())
                ->description('上架中')
                ->descriptionIcon('heroicon-m-cube')
                ->color('info'),

            Stat::make('會員', number_format(Customer::count()))
                ->description('黑名單 ' . Blacklist::where('is_active', true)->count() . ' 筆')
                ->descriptionIcon('heroicon-m-users')
                ->color('gray'),
        ];
    }
}
