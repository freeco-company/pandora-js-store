<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PopupResource\Pages;
use App\Models\Popup;
use BackedEnum;
use Filament\Forms;
use Filament\Schemas\Schema;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use UnitEnum;

class PopupResource extends Resource
{
    protected static ?string $model = Popup::class;

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-window';
    protected static string | UnitEnum | null $navigationGroup = '內容管理';
    protected static ?string $navigationLabel = '首頁彈窗';
    protected static ?string $modelLabel = '首頁彈窗';
    protected static ?string $pluralModelLabel = '首頁彈窗';
    protected static ?int $navigationSort = 4;

    public static function form(Schema $form): Schema
    {
        return $form
            ->schema([
                \Filament\Schemas\Components\Section::make('彈窗設定')->schema([
                    Forms\Components\TextInput::make('title')
                        ->required()
                        ->label('標題'),
                    Forms\Components\FileUpload::make('image')
                        ->image()
                        ->directory('popups')
                        ->label('圖片'),
                    Forms\Components\TextInput::make('link')
                        ->url()
                        ->label('點擊連結')
                        ->placeholder('https://...'),
                    Forms\Components\RichEditor::make('content')
                        ->label('內容（選填，無圖片時顯示）')
                        ->columnSpanFull(),
                    Forms\Components\Select::make('display_frequency')
                        ->options([
                            'once' => '只顯示一次',
                            'once_per_day' => '每天顯示一次',
                            'every_visit' => '每次進站顯示',
                        ])
                        ->default('once')
                        ->required()
                        ->label('顯示頻率'),
                    Forms\Components\TextInput::make('sort_order')
                        ->numeric()
                        ->default(0)
                        ->label('排序'),
                    Forms\Components\Toggle::make('is_active')
                        ->default(true)
                        ->label('啟用'),
                    Forms\Components\DateTimePicker::make('starts_at')
                        ->label('開始時間'),
                    Forms\Components\DateTimePicker::make('ends_at')
                        ->label('結束時間'),
                ])->columns(2),
            ])
            ->columns(1);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('image')->label('圖片'),
                Tables\Columns\TextColumn::make('title')
                    ->searchable()
                    ->sortable()
                    ->label('標題'),
                Tables\Columns\BadgeColumn::make('display_frequency')
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'once' => '只顯示一次',
                        'once_per_day' => '每天一次',
                        'every_visit' => '每次進站',
                        default => $state,
                    })
                    ->label('頻率'),
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
            'index' => Pages\ListPopups::route('/'),
            'create' => Pages\CreatePopup::route('/create'),
            'edit' => Pages\EditPopup::route('/{record}/edit'),
        ];
    }
}
