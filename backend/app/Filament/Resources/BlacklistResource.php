<?php

namespace App\Filament\Resources;

use App\Filament\Resources\BlacklistResource\Pages;
use App\Models\Blacklist;
use BackedEnum;
use Filament\Forms;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use UnitEnum;

class BlacklistResource extends Resource
{
    protected static ?string $model = Blacklist::class;

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-no-symbol';
    protected static string | UnitEnum | null $navigationGroup = '系統管理';
    protected static ?string $navigationLabel = '黑名單';
    protected static ?string $modelLabel = '黑名單';
    protected static ?string $pluralModelLabel = '黑名單';
    protected static ?int $navigationSort = 1;

    public static function form(Schema $form): Schema
    {
        return $form
            ->schema([
                Section::make('黑名單設定')->schema([
                    Forms\Components\TextInput::make('email')
                        ->email()
                        ->label('Email')
                        ->helperText('Email 或手機至少填一項'),
                    Forms\Components\TextInput::make('phone')
                        ->label('手機號碼'),
                    Forms\Components\TextInput::make('reason')
                        ->label('封鎖原因')
                        ->placeholder('例：貨到付款未取貨'),
                    Forms\Components\Toggle::make('is_active')
                        ->default(true)
                        ->label('啟用'),
                ])->columns(2)
                    ->description('被加入黑名單的用戶將無法使用貨到付款功能'),
            ])
            ->columns(1);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('email')
                    ->searchable()
                    ->label('Email'),
                Tables\Columns\TextColumn::make('phone')
                    ->searchable()
                    ->label('手機'),
                Tables\Columns\TextColumn::make('reason')
                    ->limit(30)
                    ->label('原因'),
                Tables\Columns\IconColumn::make('is_active')
                    ->boolean()
                    ->label('啟用'),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime('Y-m-d')
                    ->sortable()
                    ->label('建立日期'),
            ])
            ->defaultSort('created_at', 'desc')
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

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListBlacklists::route('/'),
            'create' => Pages\CreateBlacklist::route('/create'),
            'edit' => Pages\EditBlacklist::route('/{record}/edit'),
        ];
    }
}
