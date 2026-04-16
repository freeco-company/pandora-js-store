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
                        Forms\Components\TextInput::make('stock_quantity')
                            ->numeric()
                            ->default(0)
                            ->label('庫存'),
                        Forms\Components\Select::make('stock_status')
                            ->options([
                                'instock' => '有貨',
                                'outofstock' => '缺貨',
                            ])
                            ->default('instock')
                            ->label('庫存狀態'),
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
                ])->columnSpan(1),
            ])->columns(3);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('gallery')
                    ->circular()
                    ->getStateUsing(function ($record) {
                        $gallery = $record->gallery ?? [];
                        return !empty($gallery) ? [$gallery[0]] : [];
                    })
                    ->disk('public')
                    ->label('圖'),
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->label('名稱'),
                Tables\Columns\TextColumn::make('price')
                    ->money('TWD')
                    ->sortable()
                    ->label('原價'),
                Tables\Columns\TextColumn::make('combo_price')
                    ->money('TWD')
                    ->sortable()
                    ->label('搭配價'),
                Tables\Columns\TextColumn::make('vip_price')
                    ->money('TWD')
                    ->sortable()
                    ->label('VIP價'),
                Tables\Columns\IconColumn::make('is_active')
                    ->boolean()
                    ->label('啟用'),
                Tables\Columns\TextColumn::make('stock_quantity')
                    ->sortable()
                    ->label('庫存'),
            ])
            ->defaultSort('sort_order')
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')->label('啟用狀態'),
            ])
            ->actions([
                \Filament\Actions\EditAction::make(),
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
