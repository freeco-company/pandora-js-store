<?php

namespace App\Filament\Resources;

use App\Filament\Resources\VisitResource\Pages;
use App\Models\Visit;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Carbon;
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
            ->modifyQueryUsing(fn ($query) => $query->with('customer'))
            ->columns([
                Tables\Columns\TextColumn::make('visited_at')
                    ->label('時間')
                    ->dateTime('m-d H:i:s')
                    ->sortable(),

                // Short deterministic tag so the same person's rows can be
                // eyeballed together. Same visitor_id → same tag → same
                // badge color. Tag is copyable for searching / pasting.
                Tables\Columns\TextColumn::make('visitor_id')
                    ->label('訪客')
                    ->formatStateUsing(fn (?string $state) => $state ? '#' . substr($state, 0, 6) : '—')
                    ->badge()
                    ->color(fn (?string $state): string => self::hashColor($state))
                    ->searchable()
                    ->copyable(),

                Tables\Columns\TextColumn::make('referer_source')
                    ->label('來源')
                    ->badge()
                    ->color(fn (?string $state): string => match ($state) {
                        'direct' => 'success',
                        'google', 'bing', 'yahoo' => 'info',
                        'google_ads', 'facebook_ads', 'bing_ads', 'tiktok_ads', 'linkedin_ads', 'other_ads' => 'warning',
                        'facebook', 'instagram', 'line' => 'primary',
                        'bot' => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (?string $state) => match ($state) {
                        'direct' => '直接進站',
                        'google' => 'Google',
                        'google_ads' => 'Google Ads',
                        'bing' => 'Bing',
                        'bing_ads' => 'Bing Ads',
                        'yahoo' => 'Yahoo',
                        'facebook' => 'Facebook',
                        'facebook_ads' => 'Meta Ads',
                        'instagram' => 'Instagram',
                        'line' => 'LINE',
                        'tiktok_ads' => 'TikTok Ads',
                        'linkedin_ads' => 'LinkedIn Ads',
                        'other_ads' => '其他廣告',
                        'email' => 'Email',
                        'other' => '其他',
                        'bot' => '機器人',
                        default => $state ?? '—',
                    }),

                Tables\Columns\TextColumn::make('device_type')
                    ->label('裝置')
                    ->badge()
                    ->formatStateUsing(fn (?string $state) => match ($state) {
                        'mobile' => '手機',
                        'tablet' => '平板',
                        'desktop' => '桌機',
                        default => $state ?? '—',
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
                // Single-day picker — defaults to today, user can scrub to
                // any past day. whereBetween + startOfDay/endOfDay is
                // timezone-safe (honours APP_TIMEZONE=Asia/Taipei) while
                // whereDate() relies on the DB session tz and drifts when
                // the server clock is UTC.
                Tables\Filters\Filter::make('date')
                    ->label('日期')
                    ->schema([
                        \Filament\Forms\Components\DatePicker::make('value')
                            ->label('選擇日期')
                            ->native(false)
                            ->displayFormat('Y-m-d')
                            ->maxDate(today())
                            ->default(today()),
                    ])
                    ->query(function ($query, array $data) {
                        $date = $data['value'] ?? null;
                        if (! $date) return $query;
                        $day = Carbon::parse($date);
                        return $query->whereBetween('visited_at', [
                            $day->copy()->startOfDay(),
                            $day->copy()->endOfDay(),
                        ]);
                    })
                    ->indicateUsing(function (array $data): ?string {
                        if (empty($data['value'])) return null;
                        $d = Carbon::parse($data['value']);
                        return $d->isToday() ? '今日' : $d->format('Y-m-d');
                    }),

                Tables\Filters\SelectFilter::make('referer_source')
                    ->label('來源')
                    ->options([
                        'direct' => '直接進站',
                        'google' => 'Google',
                        'google_ads' => 'Google Ads',
                        'bing' => 'Bing',
                        'bing_ads' => 'Bing Ads',
                        'facebook' => 'Facebook',
                        'facebook_ads' => 'Meta Ads',
                        'instagram' => 'Instagram',
                        'line' => 'LINE',
                        'tiktok_ads' => 'TikTok Ads',
                        'linkedin_ads' => 'LinkedIn Ads',
                        'other_ads' => '其他廣告',
                        'email' => 'Email',
                        'other' => '其他',
                        'bot' => '機器人/爬蟲',
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
                        true: fn ($query) => $query->whereNotNull('customer_id'),
                        false: fn ($query) => $query->whereNull('customer_id'),
                        blank: fn ($query) => $query,
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
            fn () => Visit::whereBetween('visited_at', [today()->startOfDay(), today()->endOfDay()])
                ->where('referer_source', '!=', 'bot')
                ->distinct('visitor_id')
                ->count('visitor_id')
        );
    }

    public static function canCreate(): bool { return false; }
    public static function canEdit($record): bool { return false; }
    public static function canDelete($record): bool { return false; }

    /**
     * Map a visitor hash to one of Filament's palette slots deterministically.
     * Same visitor_id → same color across rows, so grouping is visible at a
     * glance without a "group by" UI.
     */
    public static function hashColor(?string $state): string
    {
        if (! $state) return 'gray';
        $palette = ['primary', 'success', 'warning', 'danger', 'info'];
        $idx = hexdec(substr($state, 0, 2)) % count($palette);
        return $palette[$idx];
    }
}
