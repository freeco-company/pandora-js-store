<?php

namespace App\Filament\Resources;

use App\Filament\Resources\VisitResource\Pages;
use App\Models\Visit;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * Read-only browser for the raw human visit log. List is the only page —
 * no form, create, or edit, because rows are inserted exclusively by the
 * Next.js proxy. Dashboard widget deep-links here pre-scoped to today.
 */
class VisitResource extends Resource
{
    protected static ?string $model = Visit::class;

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-globe-asia-australia';
    protected static string | UnitEnum | null $navigationGroup = '分析';
    protected static ?string $navigationLabel = '當日流量';
    protected static ?string $modelLabel = '訪客紀錄';
    protected static ?string $pluralModelLabel = '訪客紀錄';
    protected static ?int $navigationSort = 5;

    public static function form(Schema $form): Schema
    {
        // No form — rows are immutable event logs.
        return $form->schema([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $q) => $q->with('customer'))
            ->columns([
                Tables\Columns\TextColumn::make('visited_at')
                    ->label('時間')
                    ->dateTime('m-d H:i:s')
                    ->sortable(),

                Tables\Columns\TextColumn::make('referer_source')
                    ->label('來源')
                    ->badge()
                    ->colors([
                        'success' => 'direct',
                        'info'    => fn ($s) => in_array($s, ['google', 'bing', 'yahoo']),
                        'warning' => 'google_ads',
                        'primary' => fn ($s) => in_array($s, ['facebook', 'instagram', 'line']),
                        'gray'    => fn ($s) => in_array($s, ['other', 'email']),
                    ])
                    ->formatStateUsing(fn ($state) => match ($state) {
                        'direct' => '直接進站',
                        'google' => 'Google',
                        'google_ads' => 'Google Ads',
                        'bing' => 'Bing',
                        'yahoo' => 'Yahoo',
                        'facebook' => 'Facebook',
                        'instagram' => 'Instagram',
                        'line' => 'LINE',
                        'email' => 'Email',
                        'other' => '其他',
                        default => $state,
                    }),

                Tables\Columns\TextColumn::make('device_type')
                    ->label('裝置')
                    ->badge()
                    ->formatStateUsing(fn ($state) => match ($state) {
                        'mobile' => '手機',
                        'tablet' => '平板',
                        'desktop' => '桌機',
                        default => $state,
                    }),

                Tables\Columns\TextColumn::make('os')
                    ->label('系統')
                    ->description(fn ($record) => $record->os_version),

                Tables\Columns\TextColumn::make('browser')
                    ->label('瀏覽器')
                    ->description(fn ($record) => $record->browser_version),

                Tables\Columns\TextColumn::make('path')
                    ->label('頁面')
                    ->limit(40)
                    ->tooltip(fn ($record) => $record->path),

                Tables\Columns\TextColumn::make('customer.name')
                    ->label('會員')
                    ->placeholder('訪客')
                    ->searchable(),

                Tables\Columns\TextColumn::make('country')
                    ->label('國家')
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('ip')
                    ->label('IP')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->copyable(),

                Tables\Columns\TextColumn::make('utm_campaign')
                    ->label('活動')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('referer_url')
                    ->label('Referer')
                    ->limit(40)
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->tooltip(fn ($record) => $record->referer_url),

                Tables\Columns\TextColumn::make('user_agent')
                    ->label('UA')
                    ->limit(40)
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->tooltip(fn ($record) => $record->user_agent),
            ])
            ->defaultSort('visited_at', 'desc')
            ->filters([
                Tables\Filters\Filter::make('today')
                    ->label('僅今日')
                    ->default()
                    ->query(fn (Builder $q) => $q->whereDate('visited_at', today())),

                Tables\Filters\SelectFilter::make('referer_source')
                    ->label('來源')
                    ->options([
                        'direct' => '直接進站',
                        'google' => 'Google',
                        'google_ads' => 'Google Ads',
                        'bing' => 'Bing',
                        'facebook' => 'Facebook',
                        'instagram' => 'Instagram',
                        'line' => 'LINE',
                        'email' => 'Email',
                        'other' => '其他',
                    ]),

                Tables\Filters\SelectFilter::make('device_type')
                    ->label('裝置')
                    ->options([
                        'mobile' => '手機',
                        'tablet' => '平板',
                        'desktop' => '桌機',
                    ]),

                Tables\Filters\SelectFilter::make('os')
                    ->label('系統')
                    ->options([
                        'iOS' => 'iOS',
                        'AndroidOS' => 'Android',
                        'OS X' => 'macOS',
                        'Windows' => 'Windows',
                        'Linux' => 'Linux',
                    ]),

                Tables\Filters\TernaryFilter::make('customer_id')
                    ->label('會員狀態')
                    ->placeholder('全部')
                    ->trueLabel('已登入會員')
                    ->falseLabel('訪客')
                    ->queries(
                        true: fn (Builder $q) => $q->whereNotNull('customer_id'),
                        false: fn (Builder $q) => $q->whereNull('customer_id'),
                        blank: fn (Builder $q) => $q,
                    ),
            ])
            ->actions([])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListVisits::route('/'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        // Today's unique visitor count — shown as a badge next to the nav
        // item. Cached 60s so hitting any admin page doesn't re-scan the
        // table on every click.
        return (string) \Illuminate\Support\Facades\Cache::remember(
            'visits:today-uv',
            60,
            fn () => Visit::whereDate('visited_at', today())->distinct('visitor_id')->count('visitor_id')
        );
    }

    public static function canCreate(): bool { return false; }
    public static function canEdit($record): bool { return false; }
    public static function canDelete($record): bool { return false; }
}
