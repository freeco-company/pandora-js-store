<?php

namespace App\Filament\Resources;

use App\Filament\Resources\BannerResource\Pages;
use App\Models\Banner;
use BackedEnum;
use Filament\Forms;
use Filament\Schemas\Schema;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use UnitEnum;

class BannerResource extends Resource
{
    protected static ?string $model = Banner::class;

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-photo';
    protected static string | UnitEnum | null $navigationGroup = '內容管理';
    protected static ?string $navigationLabel = '輪播橫幅';
    protected static ?string $modelLabel = '輪播橫幅';
    protected static ?string $pluralModelLabel = '輪播橫幅';
    protected static ?int $navigationSort = 3;

    public static function form(Schema $form): Schema
    {
        return $form
            ->schema([
                \Filament\Schemas\Components\Section::make('橫幅設定')->schema([
                    Forms\Components\TextInput::make('title')
                        ->required()
                        ->label('標題'),
                    Forms\Components\FileUpload::make('image')
                        ->image()
                        ->directory('banners')
                        ->required()
                        ->label('桌機圖片')
                        ->helperText('建議尺寸：1920 x 600 px'),
                    Forms\Components\FileUpload::make('mobile_image')
                        ->image()
                        ->directory('banners')
                        ->label('手機圖片')
                        ->helperText('建議尺寸：750 x 900 px，留空則使用桌機圖片'),
                    Forms\Components\TextInput::make('link')
                        ->url()
                        ->label('連結網址')
                        ->placeholder('https://...'),
                    Forms\Components\TextInput::make('sort_order')
                        ->numeric()
                        ->default(0)
                        ->label('排序（數字越小越前面）'),
                    Forms\Components\Toggle::make('is_active')
                        ->default(true)
                        ->label('啟用'),
                    Forms\Components\DateTimePicker::make('starts_at')
                        ->label('開始時間')
                        ->helperText('留空表示立即生效'),
                    Forms\Components\DateTimePicker::make('ends_at')
                        ->label('結束時間')
                        ->helperText('留空表示永不過期'),
                ])->columns(2),
            ])
            ->columns(1);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->stackedOnMobile()
            ->columns([
                Tables\Columns\ImageColumn::make('image')->label('圖片'),
                Tables\Columns\TextColumn::make('title')
                    ->searchable()
                    ->sortable()
                    ->label('標題'),
                Tables\Columns\TextColumn::make('sort_order')
                    ->sortable()
                    ->label('排序'),
                Tables\Columns\IconColumn::make('is_active')
                    ->boolean()
                    ->label('啟用'),
                Tables\Columns\TextColumn::make('starts_at')
                    ->dateTime('Y-m-d H:i')
                    ->label('開始'),
                Tables\Columns\TextColumn::make('ends_at')
                    ->dateTime('Y-m-d H:i')
                    ->label('結束'),
            ])
            ->defaultSort('sort_order')
            ->actions([
                \Filament\Actions\EditAction::make(),
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
            'index' => Pages\ListBanners::route('/'),
            'create' => Pages\CreateBanner::route('/create'),
            'edit' => Pages\EditBanner::route('/{record}/edit'),
        ];
    }
}
