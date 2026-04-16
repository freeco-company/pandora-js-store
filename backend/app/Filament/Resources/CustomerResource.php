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
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\Layout\Stack::make([
                    Tables\Columns\Layout\Split::make([
                        Tables\Columns\TextColumn::make('name')
                            ->searchable()
                            ->sortable()
                            ->weight('bold')
                            ->size(\Filament\Support\Enums\TextSize::Large)
                            ->label('姓名'),
                        Tables\Columns\IconColumn::make('is_vip')
                            ->boolean()
                            ->grow(false)
                            ->label('VIP'),
                    ]),
                    Tables\Columns\TextColumn::make('email')
                        ->searchable()
                        ->sortable()
                        ->icon('heroicon-m-envelope')
                        ->copyable()
                        ->label('Email'),
                    Tables\Columns\Layout\Split::make([
                        Tables\Columns\TextColumn::make('phone')
                            ->icon('heroicon-m-phone')
                            ->placeholder('—')
                            ->label('電話'),
                        Tables\Columns\TextColumn::make('orders_count')
                            ->counts('orders')
                            ->sortable()
                            ->icon('heroicon-m-shopping-bag')
                            ->formatStateUsing(fn ($state) => "訂單 {$state}")
                            ->color(fn ($state) => $state >= 3 ? 'success' : 'gray')
                            ->grow(false)
                            ->label(''),
                    ]),
                    Tables\Columns\TextColumn::make('created_at')
                        ->since()
                        ->tooltip(fn ($record) => $record->created_at?->format('Y-m-d H:i'))
                        ->icon('heroicon-m-calendar')
                        ->color('gray')
                        ->label('註冊'),
                ])->space(1),
            ])
            ->contentGrid([
                'default' => 1,
                'md' => 2,
                'xl' => 3,
            ])
            ->paginated([12, 24, 48])
            ->defaultPaginationPageOption(24)
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\TernaryFilter::make('is_vip')->label('VIP'),
            ])
            ->recordUrl(fn ($record) => Pages\EditCustomer::getUrl(['record' => $record]));
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCustomers::route('/'),
            'edit' => Pages\EditCustomer::route('/{record}/edit'),
        ];
    }
}
