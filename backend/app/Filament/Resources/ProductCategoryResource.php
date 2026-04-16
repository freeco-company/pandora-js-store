<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProductCategoryResource\Pages;
use App\Models\ProductCategory;
use Filament\Forms;
use Filament\Schemas\Schema;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use BackedEnum;
use UnitEnum;

class ProductCategoryResource extends Resource
{
    protected static ?string $model = ProductCategory::class;

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-tag';
    protected static string | UnitEnum | null $navigationGroup = '商品管理';
    protected static ?string $navigationLabel = '商品分類';
    protected static ?string $modelLabel = '商品分類';
    protected static ?string $pluralModelLabel = '商品分類';
    protected static ?int $navigationSort = 2;

    public static function form(Schema $form): Schema
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')->required()->label('分類名稱'),
                Forms\Components\TextInput::make('slug')->required()->unique(ignoreRecord: true)->label('Slug'),
                Forms\Components\Textarea::make('description')->label('描述'),
                Forms\Components\Select::make('parent_id')
                    ->relationship('parent', 'name')
                    ->label('上層分類'),
                Forms\Components\TextInput::make('sort_order')->numeric()->default(0)->label('排序'),
                Forms\Components\FileUpload::make('image')->image()->directory('categories')->label('圖片'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->searchable()->sortable()->label('名稱'),
                Tables\Columns\TextColumn::make('slug')->label('Slug'),
                Tables\Columns\TextColumn::make('parent.name')->label('上層'),
                Tables\Columns\TextColumn::make('products_count')->counts('products')->label('商��數'),
                Tables\Columns\TextColumn::make('sort_order')->sortable()->label('排��'),
            ])
            ->defaultSort('sort_order')
            ->actions([
                \Filament\Actions\EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProductCategories::route('/'),
            'create' => Pages\CreateProductCategory::route('/create'),
            'edit' => Pages\EditProductCategory::route('/{record}/edit'),
        ];
    }
}
