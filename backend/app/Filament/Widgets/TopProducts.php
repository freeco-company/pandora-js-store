<?php

namespace App\Filament\Widgets;

use App\Models\OrderItem;
use Carbon\Carbon;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class TopProducts extends BaseWidget
{
    use InteractsWithPageFilters;

    protected static ?string $heading = '熱銷商品 TOP 10（期間內）';

    protected static ?int $sort = 5;

    protected int | string | array $columnSpan = 'full';

    protected ?string $pollingInterval = '300s';

    public function table(Table $table): Table
    {
        [$start, $end] = $this->resolveRange();

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
                    ->whereNotNull('product_id')
                    ->whereHas('order', fn (Builder $q) => $q
                        ->where('payment_status', 'paid')
                        ->whereBetween('created_at', [$start, $end])
                    )
                    ->groupBy('product_id', 'product_name')
                    ->orderByDesc('total_qty')
                    ->limit(10)
            )
            ->columns([
                Tables\Columns\TextColumn::make('product_name')
                    ->label('商品名稱')
                    ->limit(40)
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

    protected function resolveRange(): array
    {
        $start = $this->pageFilters['startDate'] ?? null;
        $end = $this->pageFilters['endDate'] ?? null;

        $start = $start ? Carbon::parse($start)->startOfDay() : now()->subDays(29)->startOfDay();
        $end = $end ? Carbon::parse($end)->endOfDay() : now()->endOfDay();

        return [$start, $end];
    }
}
