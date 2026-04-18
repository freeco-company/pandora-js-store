<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CustomerResource\Pages;
use App\Models\Customer;
use Filament\Forms;
use Filament\Schemas\Schema;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use BackedEnum;
use UnitEnum;

class CustomerResource extends Resource
{
    protected static ?string $model = Customer::class;

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-users';
    protected static string | UnitEnum | null $navigationGroup = '訂單管理';
    protected static ?string $navigationLabel = '客戶';
    protected static ?string $modelLabel = '客戶';
    protected static ?string $pluralModelLabel = '客戶';
    protected static ?int $navigationSort = 2;

    public static function form(Schema $form): Schema
    {
        return $form
            ->schema([
                \Filament\Schemas\Components\Section::make('基本資料')->schema([
                    Forms\Components\TextInput::make('name')->label('姓名'),
                    Forms\Components\TextInput::make('email')->email()->required()->label('Email'),
                    Forms\Components\TextInput::make('phone')->label('電話'),
                    Forms\Components\Toggle::make('is_vip')->label('VIP 會員'),
                ])->columns(2),

                \Filament\Schemas\Components\Section::make('地址')->schema([
                    Forms\Components\TextInput::make('address_city')->label('縣市'),
                    Forms\Components\TextInput::make('address_district')->label('區域'),
                    Forms\Components\TextInput::make('address_zip')->label('郵遞區號'),
                    Forms\Components\TextInput::make('address_detail')->label('詳細地址'),
                ])->columns(2),
            ])
            ->columns(1); // Top-level: stack vertically for readability
    }

    public static function table(Table $table): Table
    {
        return $table
            ->stackedOnMobile()
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->placeholder('—')
                    ->label('姓名'),

                Tables\Columns\TextColumn::make('email')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->label('Email'),

                Tables\Columns\TextColumn::make('phone')
                    ->searchable()
                    ->placeholder('—')
                    ->label('電話'),

                Tables\Columns\IconColumn::make('is_vip')
                    ->boolean()
                    ->sortable()
                    ->label('VIP'),

                Tables\Columns\TextColumn::make('orders_count')
                    ->counts('orders')
                    ->sortable()
                    ->alignEnd()
                    ->color(fn ($state) => $state >= 3 ? 'success' : 'gray')
                    ->label('訂單數'),

                Tables\Columns\TextColumn::make('address_city')
                    ->placeholder('—')
                    ->description(fn ($record) => $record->address_district ?: null)
                    ->toggleable()
                    ->label('縣市'),

                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime('Y-m-d')
                    ->sortable()
                    ->label('註冊'),
            ])
            ->paginated([25, 50, 100, 200])
            ->defaultPaginationPageOption(25)
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\TernaryFilter::make('is_vip')->label('VIP'),
                Tables\Filters\Filter::make('has_orders')
                    ->label('有下過單')
                    ->query(fn ($q) => $q->has('orders')),
            ])
            ->recordUrl(fn ($record) => Pages\EditCustomer::getUrl(['record' => $record]))
            ->actions([
                \Filament\Actions\EditAction::make()->iconButton(),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            CustomerResource\RelationManagers\OrdersRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCustomers::route('/'),
            'edit' => Pages\EditCustomer::route('/{record}/edit'),
        ];
    }
}
