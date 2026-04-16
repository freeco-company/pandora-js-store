<?php

namespace App\Filament\Widgets;

use App\Models\Order;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class LatestOrders extends BaseWidget
{
    protected static ?int $sort = 2;
    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->query(Order::query()->latest()->limit(10))
            ->columns([
                Tables\Columns\TextColumn::make('order_number')->label('訂單編號'),
                Tables\Columns\TextColumn::make('customer.name')->label('客戶'),
                Tables\Columns\BadgeColumn::make('status')
                    ->colors([
                        'warning' => 'pending',
                        'primary' => 'processing',
                        'success' => 'completed',
                        'danger' => fn ($state) => in_array($state, ['cancelled', 'refunded']),
                    ])
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'pending' => '待處理',
                        'processing' => '處理中',
                        'completed' => '已完成',
                        'cancelled' => '已取消',
                        'refunded' => '已退款',
                        default => $state,
                    })
                    ->label('狀態'),
                Tables\Columns\TextColumn::make('total')->money('TWD')->label('金額'),
                Tables\Columns\TextColumn::make('created_at')->dateTime('Y-m-d H:i')->label('時間'),
            ]);
    }
}
