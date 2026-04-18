<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProductResource\Pages;
use App\Models\Product;
use BackedEnum;
use Filament\Forms;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use UnitEnum;

class ProductResource extends Resource
{
    protected static ?string $model = Product::class;

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-shopping-bag';
    protected static string | UnitEnum | null $navigationGroup = '商品管理';
    protected static ?string $navigationLabel = '商品';
    protected static ?string $modelLabel = '商品';
    protected static ?string $pluralModelLabel = '商品';
    protected static ?int $navigationSort = 1;

    public static function form(Schema $form): Schema
    {
        return $form
            ->schema([
                Group::make()->schema([
                    Section::make('基本資訊')->schema([
                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->label('商品名稱'),
                        Forms\Components\TextInput::make('slug')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->label('URL Slug'),
                        Forms\Components\RichEditor::make('description')
                            ->label('商品描述')
                            ->columnSpanFull(),
                        Forms\Components\Textarea::make('short_description')
                            ->label('簡短描述')
                            ->rows(3)
                            ->columnSpanFull(),
                    ])->columns(2),

                    Section::make('價格設定')->schema([
                        Forms\Components\TextInput::make('price')
                            ->numeric()
                            ->required()
                            ->prefix('$')
                            ->label('原價'),
                        Forms\Components\TextInput::make('combo_price')
                            ->numeric()
                            ->prefix('$')
                            ->label('1+1 搭配價'),
                        Forms\Components\TextInput::make('vip_price')
                            ->numeric()
                            ->prefix('$')
                            ->label('VIP 價'),
                        Forms\Components\TextInput::make('sale_price')
                            ->numeric()
                            ->prefix('$')
                            ->label('特價'),
                    ])->columns(2),
                ])->columnSpan(2),

                Group::make()->schema([
                    Section::make('狀態')->schema([
                        Forms\Components\Toggle::make('is_active')
                            ->default(true)
                            ->label('啟用'),
                        Forms\Components\TextInput::make('sku')
                            ->label('SKU'),
                        Forms\Components\Select::make('stock_status')
                            ->options([
                                'instock' => '有貨（不限庫存）',
                                'outofstock' => '缺貨 / 暫停銷售',
                            ])
                            ->default('instock')
                            ->label('庫存狀態')
                            ->helperText('選「有貨」即為不限庫存；選「缺貨」商品頁會顯示售完。'),
                        Forms\Components\TextInput::make('stock_quantity')
                            ->numeric()
                            ->default(0)
                            ->label('庫存數量（參考用）')
                            ->helperText('目前不限制庫存數量，此欄僅供內部參考。'),
                        Forms\Components\TextInput::make('sort_order')
                            ->numeric()
                            ->default(0)
                            ->label('排序'),
                    ]),

                    Section::make('商品圖組（第一張為封面）')->schema([
                        Forms\Components\FileUpload::make('gallery')
                            ->image()
                            ->multiple()
                            ->reorderable()
                            ->directory('products')
                            ->disk('public')
                            ->label('圖片')
                            ->helperText('可拖曳排序，第一張自動作為封面圖'),
                    ]),

                    Section::make('分類')->schema([
                        Forms\Components\Select::make('categories')
                            ->relationship('categories', 'name')
                            ->multiple()
                            ->preload()
                            ->label('商品分類'),
                    ]),

                    Section::make('衛福部健康食品認證')->schema([
                        Forms\Components\TextInput::make('hf_cert_no')
                            ->label('認證字號')
                            ->placeholder('例：衛部健食字第 A00455 號')
                            ->helperText('僅限衛福部核可的健康食品填寫，前台會顯示小綠人 badge。'),
                        Forms\Components\TextInput::make('hf_cert_claim')
                            ->label('核可保健功效')
                            ->placeholder('例：輔助調節血脂')
                            ->helperText('與認證字號同一份核可文件上的功效宣稱，不得自行擴增。'),
                    ])->collapsible()->collapsed(fn ($record) => ! $record?->hf_cert_no),

                    Section::make('SEO 設定')->schema([
                        Forms\Components\TextInput::make('seoMeta.title')
                            ->label('Meta Title')
                            ->placeholder('留空則使用商品名稱')
                            ->maxLength(70)
                            ->helperText('建議 60 字內，Google 搜尋結果標題'),
                        Forms\Components\Textarea::make('seoMeta.description')
                            ->label('Meta Description')
                            ->placeholder('留空則使用簡短描述')
                            ->rows(3)
                            ->maxLength(160)
                            ->helperText('建議 120-160 字，Google 搜尋結果摘要'),
                        Forms\Components\TextInput::make('seoMeta.focus_keyword')
                            ->label('Focus Keyword')
                            ->placeholder('例：婕樂纖酵素'),
                        Forms\Components\FileUpload::make('seoMeta.og_image')
                            ->image()
                            ->directory('seo')
                            ->disk('public')
                            ->label('OG Image')
                            ->helperText('社群分享圖，建議 1200×630px。留空使用封面圖。'),
                    ])->collapsible()->collapsed(fn ($record) => ! $record?->seoMeta?->title),
                ])->columnSpan(1),
            ])->columns(3);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('gallery')
                    ->square()
                    ->size(56)
                    ->getStateUsing(function ($record) {
                        $gallery = $record->gallery ?? [];
                        return ! empty($gallery) ? [$gallery[0]] : [];
                    })
                    ->disk('public')
                    ->label('圖'),

                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->description(fn ($record) => $record->sku ?: null)
                    ->limit(40)
                    ->label('商品名稱'),

                Tables\Columns\TextColumn::make('price')
                    ->money('TWD', 0)
                    ->alignEnd()
                    ->sortable()
                    ->label('單件'),

                Tables\Columns\TextColumn::make('combo_price')
                    ->money('TWD', 0)
                    ->alignEnd()
                    ->color('info')
                    ->placeholder('—')
                    ->label('搭配價'),

                Tables\Columns\TextColumn::make('vip_price')
                    ->money('TWD', 0)
                    ->alignEnd()
                    ->color('warning')
                    ->placeholder('—')
                    ->label('VIP 價'),

                Tables\Columns\TextColumn::make('stock_status')
                    ->badge()
                    ->formatStateUsing(fn (?string $state) => match ($state) {
                        'instock' => '有貨',
                        'outofstock' => '缺貨',
                        default => $state ?? '-',
                    })
                    ->color(fn (?string $state) => $state === 'outofstock' ? 'danger' : 'success')
                    ->label('庫存')
                    ->toggleable(),

                Tables\Columns\IconColumn::make('hf_cert_no')
                    ->icon('heroicon-m-shield-check')
                    ->getStateUsing(fn ($record) => (bool) $record->hf_cert_no)
                    ->color('success')
                    ->tooltip(fn ($record) => $record->hf_cert_no ?: null)
                    ->label('健食')
                    ->toggleable(),

                Tables\Columns\ToggleColumn::make('is_active')
                    ->label('啟用'),

                Tables\Columns\TextColumn::make('sort_order')
                    ->sortable()
                    ->alignEnd()
                    ->label('排序')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->paginated([25, 50, 100, 200])
            ->defaultPaginationPageOption(25)
            ->defaultSort('sort_order')
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')->label('啟用狀態'),
                Tables\Filters\SelectFilter::make('stock_status')
                    ->options(['instock' => '有貨', 'outofstock' => '缺貨'])
                    ->label('庫存'),
                Tables\Filters\Filter::make('has_cert')
                    ->label('健康食品認證')
                    ->query(fn ($q) => $q->whereNotNull('hf_cert_no')),
            ])
            ->recordUrl(fn ($record) => Pages\EditProduct::getUrl(['record' => $record]))
            ->actions([
                \Filament\Actions\EditAction::make()->iconButton(),
            ])
            ->bulkActions([
                \Filament\Actions\BulkActionGroup::make([
                    \Filament\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProducts::route('/'),
            'create' => Pages\CreateProduct::route('/create'),
            'edit' => Pages\EditProduct::route('/{record}/edit'),
        ];
    }
}
