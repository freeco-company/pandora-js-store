<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ReviewResource\Pages;
use App\Models\Review;
use Filament\Forms;
use Filament\Schemas\Schema;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use BackedEnum;
use UnitEnum;

class ReviewResource extends Resource
{
    protected static ?string $model = Review::class;

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-star';
    protected static string | UnitEnum | null $navigationGroup = '會員管理';
    protected static ?string $navigationLabel = '商品評論';
    protected static ?string $modelLabel = '評論';
    protected static ?string $pluralModelLabel = '評論';
    protected static ?int $navigationSort = 4;

    public static function form(Schema $form): Schema
    {
        return $form
            ->schema([
                Forms\Components\Select::make('product_id')
                    ->relationship('product', 'name')
                    ->searchable()
                    ->required()
                    ->label('商品'),
                Forms\Components\Select::make('customer_id')
                    ->relationship('customer', 'name')
                    ->searchable()
                    ->nullable()
                    ->label('會員'),
                Forms\Components\TextInput::make('reviewer_name')
                    ->required()
                    ->label('顯示名稱'),
                Forms\Components\Select::make('rating')
                    ->options([5 => '⭐⭐⭐⭐⭐', 4 => '⭐⭐⭐⭐', 3 => '⭐⭐⭐', 2 => '⭐⭐', 1 => '⭐'])
                    ->required()
                    ->label('評分'),
                Forms\Components\Textarea::make('content')
                    ->nullable()
                    ->rows(3)
                    ->label('內容'),
                Forms\Components\Toggle::make('is_verified_purchase')->default(false)->label('已驗證購買'),
                Forms\Components\Toggle::make('is_seeded')->default(false)->label('種子資料'),
                Forms\Components\Toggle::make('is_visible')->default(true)->label('顯示'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('product.name')->limit(20)->searchable()->sortable()->label('商品'),
                Tables\Columns\TextColumn::make('reviewer_name')->searchable()->label('評論者'),
                Tables\Columns\TextColumn::make('rating')
                    ->formatStateUsing(fn (int $state) => str_repeat('⭐', $state))
                    ->label('評分'),
                Tables\Columns\TextColumn::make('content')->limit(30)->label('內容'),
                Tables\Columns\IconColumn::make('is_verified_purchase')->boolean()->label('已購買'),
                Tables\Columns\IconColumn::make('is_seeded')->boolean()->label('種子'),
                Tables\Columns\IconColumn::make('is_visible')->boolean()->label('顯示'),
                Tables\Columns\TextColumn::make('created_at')->dateTime('Y-m-d')->sortable()->label('日期'),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\TernaryFilter::make('is_seeded')->label('種子資料'),
                Tables\Filters\TernaryFilter::make('is_visible')->label('顯示狀態'),
            ])
            ->actions([
                \Filament\Actions\EditAction::make(),
                \Filament\Actions\DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListReviews::route('/'),
            'create' => Pages\CreateReview::route('/create'),
            'edit' => Pages\EditReview::route('/{record}/edit'),
        ];
    }
}
