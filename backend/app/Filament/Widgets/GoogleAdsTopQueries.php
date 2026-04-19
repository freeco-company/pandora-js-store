<?php

namespace App\Filament\Widgets;

use App\Services\GoogleAdsService;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Table of top search terms from Google Ads (what users actually typed)
 * over the last 7 days, sorted by spend.
 *
 * Rows with 0 conversions are visually de-emphasized so marketing can
 * quickly spot negative-keyword candidates.
 *
 * Hidden when API not configured.
 */
class GoogleAdsTopQueries extends BaseWidget
{
    protected static ?int $sort = 8;

    protected int | string | array $columnSpan = 'full';

    protected static ?string $heading = 'Google Ads 搜尋字詞（近 7 天，Top 15）';

    public static function canView(): bool
    {
        return GoogleAdsService::fromConfig()->isConfigured();
    }

    /**
     * Filament TableWidget supports a `records()` method (in addition to
     * `query()`) for non-Eloquent data sources. We wrap the API result in
     * an in-memory Collection, 15 min cache.
     */
    public function table(Table $table): Table
    {
        return $table
            ->records(fn () => Cache::remember(
                'google_ads:top_queries:7d',
                now()->addMinutes(15),
                fn () => collect(GoogleAdsService::fromConfig()->getTopSearchTerms(7, 15))
                    ->map(fn ($row, $i) => array_merge($row, ['id' => $i])) // TableWidget wants a stable id
                    ->values()
                    ->all()
            ))
            ->columns([
                Tables\Columns\TextColumn::make('term')
                    ->label('搜尋字詞')
                    ->wrap()
                    ->weight(fn ($record) => $record['conversions'] > 0 ? 'bold' : 'normal')
                    ->color(fn ($record) => $record['conversions'] > 0 ? 'primary' : 'gray'),

                Tables\Columns\TextColumn::make('spend')
                    ->label('花費')
                    ->alignEnd()
                    ->formatStateUsing(fn ($state) => 'NT$' . number_format((int) $state)),

                Tables\Columns\TextColumn::make('clicks')
                    ->label('點擊')
                    ->alignEnd()
                    ->formatStateUsing(fn ($state) => number_format((int) $state)),

                Tables\Columns\TextColumn::make('impressions')
                    ->label('曝光')
                    ->alignEnd()
                    ->formatStateUsing(fn ($state) => number_format((int) $state))
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('conversions')
                    ->label('轉換')
                    ->alignEnd()
                    ->badge()
                    ->color(fn ($state) => ((float) $state) > 0 ? 'success' : 'gray')
                    ->formatStateUsing(fn ($state) => (string) (float) $state),

                Tables\Columns\TextColumn::make('cpa')
                    ->label('CPA')
                    ->alignEnd()
                    ->state(fn ($record) => $record['conversions'] > 0
                        ? (int) round($record['spend'] / $record['conversions'])
                        : null)
                    ->formatStateUsing(fn ($state) => $state ? 'NT$' . number_format($state) : '—'),
            ])
            ->emptyStateHeading('尚無資料')
            ->emptyStateDescription('近 7 天還沒有搜尋字詞資料，或 API 快取尚未建立。');
    }
}
