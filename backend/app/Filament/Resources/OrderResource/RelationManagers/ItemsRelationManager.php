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
                Tables\Columns\TextColumn::make('display_name')
                    ->label('商品')
                    ->weight('bold')
                    ->wrap()
                    ->description(fn ($record) => $record->bundle_is_gift ? '贈品' : null),
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
            ->defaultGroup(
                // bundle_group is a computed accessor on OrderItem (parsed
                // from product_name prefix 【…】), NOT a DB column. Filament
                // would otherwise emit `ORDER BY bundle_group` and explode
                // with "Column not found". Sort by product_name instead —
                // same bundle prefix naturally clusters rows together.
                Tables\Grouping\Group::make('bundle_group')
                    ->label('套組')
                    ->getKeyFromRecordUsing(fn ($record) => $record->bundle_group)
                    ->getTitleFromRecordUsing(fn ($record) => $record->bundle_group)
                    ->orderQueryUsing(fn ($query, string $direction) => $query->orderBy('product_name', $direction))
                    ->titlePrefixedWithLabel(false)
                    ->collapsible(false)
            )
            ->groupingSettingsHidden()
            ->paginated(false);
    }
}
