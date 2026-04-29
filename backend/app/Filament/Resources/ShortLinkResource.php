<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ShortLinkResource\Pages;
use App\Models\Bundle;
use App\Models\Order;
use App\Models\ShortLink;
use BackedEnum;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Validation\Rule;
use UnitEnum;

/**
 * Marketing short links — /p/{code} on the storefront 302s to a long URL
 * with utm_*. Created either ad-hoc here or via the row action on the
 * Campaign → Bundles list (BundlesRelationManager).
 */
class ShortLinkResource extends Resource
{
    protected static ?string $model = ShortLink::class;

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-link';
    protected static string | UnitEnum | null $navigationGroup = '行銷管理';
    protected static ?string $navigationLabel = '短網址';
    protected static ?string $modelLabel = '短網址';
    protected static ?string $pluralModelLabel = '短網址';
    protected static ?int $navigationSort = 5;

    public static function form(Schema $form): Schema
    {
        return $form
            ->schema([
                \Filament\Schemas\Components\Section::make('短網址設定')->schema([
                    Forms\Components\TextInput::make('label')
                        ->required()
                        ->maxLength(120)
                        ->label('標籤')
                        ->helperText('後台識別用，例：母親節 IG 限動'),
                    Forms\Components\TextInput::make('code')
                        ->required()
                        ->maxLength(40)
                        ->minLength(3)
                        ->regex('/^[a-z0-9-]+$/')
                        ->rule(fn ($record) => Rule::unique('short_links', 'code')->ignore($record?->id))
                        ->label('短網址 code')
                        ->placeholder('pandora-mday-ig-story')
                        ->helperText('純小寫英數 + 連字號，3-40 字。例：pandora-mday-ig-story → /p/pandora-mday-ig-story'),
                    Forms\Components\TextInput::make('target_url')
                        ->required()
                        ->url()
                        ->maxLength(500)
                        ->label('導向網址（含 UTM）')
                        ->placeholder('https://pandora.js-store.com.tw/bundles/...?utm_source=instagram&...')
                        ->columnSpanFull(),
                    Forms\Components\Select::make('bundle_id')
                        ->label('關聯套組（選填）')
                        ->options(fn () => Bundle::orderBy('name')->pluck('name', 'id'))
                        ->searchable()
                        ->nullable(),
                    Forms\Components\TextInput::make('campaign')
                        ->maxLength(128)
                        ->label('Campaign 標籤')
                        ->helperText('= utm_campaign，便於後台篩選與訂單對應'),
                    Forms\Components\DateTimePicker::make('expires_at')
                        ->label('過期時間（選填）')
                        ->helperText('過期後 /p/{code} 會 fallback 回首頁'),
                ])->columns(2),
            ])
            ->columns(1);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->stackedOnMobile()
            ->columns([
                Tables\Columns\TextColumn::make('code')
                    ->label('短網址')
                    ->searchable()
                    ->copyable()
                    ->copyableState(fn ($record) => $record->fullUrl())
                    ->copyMessage('已複製短網址')
                    ->formatStateUsing(fn ($state) => '/p/' . $state)
                    ->weight('bold')
                    ->color('primary'),
                Tables\Columns\TextColumn::make('label')
                    ->label('標籤')
                    ->searchable()
                    ->wrap(),
                Tables\Columns\TextColumn::make('bundle.name')
                    ->label('套組')
                    ->placeholder('—')
                    ->searchable(),
                Tables\Columns\TextColumn::make('campaign')
                    ->label('Campaign')
                    ->searchable()
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('click_count')
                    ->label('點擊')
                    ->alignEnd()
                    ->sortable()
                    ->numeric(),
                Tables\Columns\TextColumn::make('orders_count')
                    ->label('訂單')
                    ->alignEnd()
                    ->state(fn ($record) => $record->campaign
                        ? Order::where('utm_campaign', $record->campaign)->count()
                        : 0)
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('expires_at')
                    ->label('過期')
                    ->dateTime('Y-m-d H:i')
                    ->placeholder('永久'),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('建立時間')
                    ->dateTime('Y-m-d H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('campaign')
                    ->label('Campaign')
                    ->options(fn () => ShortLink::query()
                        ->whereNotNull('campaign')
                        ->distinct()
                        ->pluck('campaign', 'campaign')),
            ])
            ->actions([
                \Filament\Actions\Action::make('viewQr')
                    ->label('QR Code')
                    ->icon('heroicon-o-qr-code')
                    ->color('gray')
                    ->modalHeading(fn ($record) => 'QR Code — ' . $record->label)
                    ->modalContent(fn ($record) => view('filament.short-link-qr', [
                        'url' => $record->fullUrl(),
                        'qr' => self::renderQrSvg($record->fullUrl()),
                    ]))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('關閉'),
                \Filament\Actions\EditAction::make(),
                \Filament\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                \Filament\Actions\BulkActionGroup::make([
                    \Filament\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListShortLinks::route('/'),
            'create' => Pages\CreateShortLink::route('/create'),
            'edit' => Pages\EditShortLink::route('/{record}/edit'),
        ];
    }

    /**
     * Render a QR code as inline SVG (no external service, no temp files).
     * 220×220 ECC=M is plenty for a ~40-char short URL.
     */
    private static function renderQrSvg(string $url): string
    {
        $options = new \chillerlan\QRCode\QROptions([
            'outputType' => \chillerlan\QRCode\QRCode::OUTPUT_MARKUP_SVG,
            'eccLevel' => \chillerlan\QRCode\QRCode::ECC_M,
            'imageBase64' => false,
            'svgViewBoxSize' => 30,
            'addQuietzone' => true,
            'quietzoneSize' => 2,
        ]);
        return (new \chillerlan\QRCode\QRCode($options))->render($url);
    }
}
