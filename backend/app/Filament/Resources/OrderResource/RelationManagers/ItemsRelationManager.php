<?php

namespace App\Filament\Resources\OrderResource\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class ItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'items';
    protected static ?string $title = '訂單商品';

    public function table(Table $table): Table
    {
        return $table
            ->stackedOnMobile()
            ->columns([
                Tables\Columns\ImageColumn::make('product.gallery')
                    ->label('')
                    ->square()
                    ->size(56)
                    ->getStateUsing(function ($record) {
                        $gallery = $record->product?->gallery ?? [];
                        return !empty($gallery) ? [$gallery[0]] : [];
                    })
                    ->disk('public'),
                Tables\Columns\TextColumn::make('product_name')
                    ->label('商品')
                    ->weight('bold')
                    ->wrap(),
                Tables\Columns\TextColumn::make('quantity')
                    ->label('數量')
                    ->alignCenter(),
                Tables\Columns\TextColumn::make('unit_price')
                    ->label('單價')
                    ->money('TWD'),
                Tables\Columns\TextColumn::make('subtotal')
                    ->label('小計')
                    ->money('TWD')
                    ->weight('bold')
                    ->color('primary'),
            ])
            ->paginated(false);
    }
}
