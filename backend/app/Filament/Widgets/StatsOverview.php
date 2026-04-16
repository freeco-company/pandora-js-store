<?php

namespace App\Filament\Widgets;

use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StatsOverview extends BaseWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $thisMonth = now()->startOfMonth();

        return [
            Stat::make('本月訂單', Order::where('created_at', '>=', $thisMonth)->count())
                ->description('本月新訂單數')
                ->descriptionIcon('heroicon-m-shopping-bag')
                ->color('primary'),

            Stat::make('本月營收', '$' . number_format(Order::where('created_at', '>=', $thisMonth)->where('status', '!=', 'cancelled')->sum('total')))
                ->description('不含已取消')
                ->descriptionIcon('heroicon-m-currency-dollar')
                ->color('success'),

            Stat::make('商品數', Product::where('is_active', true)->count())
                ->description('上架中商品')
                ->descriptionIcon('heroicon-m-cube')
                ->color('info'),

            Stat::make('客戶數', Customer::count())
                ->description('總註冊客戶')
                ->descriptionIcon('heroicon-m-users')
                ->color('warning'),
        ];
    }
}
