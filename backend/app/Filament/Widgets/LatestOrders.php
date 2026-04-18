<?php

namespace App\Filament\Widgets;

use App\Models\Order;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class LatestOrders extends BaseWidget
{
    protected static ?int $sort = 7;
    protected int|string|array $columnSpan = 'full';

    protected ?string $pollingInterval = '60s';

    protected static ?string $heading = '最新訂單（即時，不受期間篩選影響）';

    public function table(Table $table): Table
    {
        return $table
            ->query(Order::query()->with('customer')->latest()->limit(10))
            ->columns([
                Tables\Columns\TextColumn::make('order_number')
                    ->label('訂單編號')
                    ->weight('bold')
                    ->copyable(),
                Tables\Columns\TextColumn::make('customer.name')
                    ->label('客戶')
                    ->description(fn ($record) => $record->customer?->email),
                Tables\Columns\BadgeColumn::make('status')
                    ->colors([
                        'warning' => 'pending',
                        'primary' => fn ($state) => in_array($state, ['processing', 'shipped']),
                        'success' => 'completed',
                        'danger' => fn ($state) => in_array($state, ['cancelled', 'refunded', 'cod_no_pickup']),
                    ])
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'pending' => '待處理',
                        'processing' => '處理中',
                        'shipped' => '已出貨',
                        'completed' => '已完成',
                        'cancelled' => '已取消',
                        'refunded' => '已退款',
                        'cod_no_pickup' => '未取件',
                        default => $state,
                    })
                    ->label('狀態'),
                Tables\Columns\TextColumn::make('total')
                    ->money('TWD')
                    ->alignEnd()
                    ->label('金額'),
                Tables\Columns\TextColumn::make('shipping_method')
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'home_delivery' => '🏠 宅配',
                        'cvs_711' => '🏪 7-11',
                        'cvs_family' => '🏪 全家',
                        default => $state ?? '-',
                    })
                    ->label('配送'),
                Tables\Columns\TextColumn::make('payment_status')
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'paid' => '已付款',
                        'unpaid' => '未付款',
                        'refunded' => '已退款',
                        'failed' => '付款失敗',
                        default => $state ?? '-',
                    })
                    ->badge()
                    ->color(fn (?string $state) => match ($state) {
                        'paid' => 'success',
                        'unpaid' => 'warning',
                        'failed' => 'danger',
                        default => 'gray',
                    })
                    ->label('付款'),
                Tables\Columns\TextColumn::make('created_at')
                    ->since()
                    ->tooltip(fn ($record) => $record->created_at?->format('Y-m-d H:i'))
                    ->label('建立'),
            ])
            ->recordUrl(fn ($record) => \App\Filament\Resources\OrderResource::getUrl('edit', ['record' => $record]));
    }
}
