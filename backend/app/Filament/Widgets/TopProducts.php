<?php

namespace App\Filament\Widgets;

use App\Models\OrderItem;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class TopProducts extends BaseWidget
{
    protected static ?string $heading = '近 30 日熱銷商品 TOP 10';

    protected static ?int $sort = 4;

    protected int|string|array $columnSpan = 'full';

    protected ?string $pollingInterval = '300s';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                OrderItem::query()
                    ->select(
                        'product_id',
                        'product_name',
                        DB::raw('SUM(quantity) as total_qty'),
                        DB::raw('SUM(subtotal) as total_revenue'),
                        DB::raw('COUNT(DISTINCT order_id) as order_count'),
                    )
                    ->whereHas('order', fn (Builder $q) => $q
                        ->where('payment_status', 'paid')
                        ->where('created_at', '>=', now()->subDays(30))
                    )
                    ->groupBy('product_id', 'product_name')
                    ->orderByDesc('total_qty')
                    ->limit(10)
            )
            ->columns([
                Tables\Columns\TextColumn::make('product_name')
                    ->label('商品名稱')
                    ->limit(30)
                    ->searchable(),

                Tables\Columns\TextColumn::make('total_qty')
                    ->label('銷量')
                    ->numeric()
                    ->sortable()
                    ->alignEnd(),

                Tables\Columns\TextColumn::make('order_count')
                    ->label('訂單數')
                    ->numeric()
                    ->alignEnd(),

                Tables\Columns\TextColumn::make('total_revenue')
                    ->label('營收')
                    ->money('TWD', 0)
                    ->sortable()
                    ->alignEnd(),
            ])
            ->paginated(false);
    }
}
