<?php

namespace App\Filament\Resources\CustomerResource\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

/**
 * Customer edit page → tab showing all of this customer's orders,
 * with a link out to the full order edit screen.
 */
class OrdersRelationManager extends RelationManager
{
    protected static string $relationship = 'orders';

    protected static ?string $title = '訂單';

    public function form(Schema $form): Schema
    {
        // Inline-edit is low-value here — editing happens via the dedicated
        // order page. Keep the schema empty; actions link out instead.
        return $form->schema([]);
    }

    public function table(Table $table): Table
    {
        $statusLabels = [
            'pending' => '待處理',
            'processing' => '處理中',
            'shipped' => '已出貨',
            'completed' => '已完成',
            'cancelled' => '已取消',
            'refunded' => '已退款',
            'cod_no_pickup' => '未取件',
        ];

        return $table
            ->stackedOnMobile()
            ->recordTitleAttribute('order_number')
            ->columns([
                Tables\Columns\TextColumn::make('order_number')
                    ->searchable()
                    ->copyable()
                    ->weight('bold')
                    ->label('訂單編號'),

                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime('m/d H:i')
                    ->sortable()
                    ->label('建立時間'),

                Tables\Columns\TextColumn::make('shipping_method')
                    ->badge()
                    ->formatStateUsing(fn (?string $state) => match ($state) {
                        'home_delivery' => '宅配',
                        'cvs_711' => '7-11',
                        'cvs_family' => '全家',
                        default => $state ?? '—',
                    })
                    ->color(fn (?string $state) => match ($state) {
                        'home_delivery' => 'info',
                        'cvs_711', 'cvs_family' => 'warning',
                        default => 'gray',
                    })
                    ->label('配送方式'),

                Tables\Columns\TextColumn::make('payment_status')
                    ->badge()
                    ->formatStateUsing(fn (?string $state) => match ($state) {
                        'paid' => '已付款',
                        'unpaid', 'pending' => '未付款',
                        'refunded' => '已退款',
                        'failed' => '付款失敗',
                        default => $state ?? '—',
                    })
                    ->color(fn (?string $state) => match ($state) {
                        'paid' => 'success',
                        'unpaid', 'pending' => 'warning',
                        'failed' => 'danger',
                        'refunded' => 'gray',
                        default => 'gray',
                    })
                    ->label('付款狀態'),

                Tables\Columns\TextColumn::make('total')
                    ->money('TWD', 0)
                    ->sortable()
                    ->alignEnd()
                    ->weight('bold')
                    ->color('primary')
                    ->label('金額'),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (?string $state) => $statusLabels[$state] ?? $state ?? '—')
                    ->label('狀態'),
            ])
            ->defaultSort('created_at', 'desc')
            ->paginated([10, 25, 50])
            ->defaultPaginationPageOption(10)
            ->recordUrl(fn ($record) => \App\Filament\Resources\OrderResource::getUrl('edit', ['record' => $record]))
            ->headerActions([]) // no "create order from customer page" — orders go through checkout
            ->actions([]);
    }
}
