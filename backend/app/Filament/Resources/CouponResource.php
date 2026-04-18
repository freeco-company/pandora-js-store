<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CouponResource\Pages;
use App\Models\Coupon;
use Filament\Forms;
use Filament\Schemas\Schema;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use BackedEnum;
use UnitEnum;

class CouponResource extends Resource
{
    protected static ?string $model = Coupon::class;

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-ticket';
    protected static string | UnitEnum | null $navigationGroup = '商品管理';
    protected static ?string $navigationLabel = '優惠券';
    protected static ?string $modelLabel = '優惠券';
    protected static ?string $pluralModelLabel = '優惠券';
    protected static ?int $navigationSort = 3;

    public static function form(Schema $form): Schema
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('code')->required()->unique(ignoreRecord: true)->label('優惠碼'),
                Forms\Components\Select::make('type')
                    ->options(['fixed' => '固定金額', 'percentage' => '百分比'])
                    ->required()
                    ->label('類型'),
                Forms\Components\TextInput::make('value')->numeric()->required()->label('折扣值'),
                Forms\Components\TextInput::make('min_amount')->numeric()->label('最低消費'),
                Forms\Components\TextInput::make('max_uses')->numeric()->label('使用上限'),
                Forms\Components\TextInput::make('used_count')->numeric()->default(0)->disabled()->label('已使用次數'),
                Forms\Components\DateTimePicker::make('expires_at')->label('到期日'),
                Forms\Components\Toggle::make('is_active')->default(true)->label('啟用'),
            ])
            ->columns(1);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->stackedOnMobile()
            ->columns([
                Tables\Columns\TextColumn::make('code')->searchable()->sortable()->label('優惠碼'),
                Tables\Columns\TextColumn::make('type')
                    ->formatStateUsing(fn (string $state) => $state === 'fixed' ? '固定' : '%')
                    ->label('類型'),
                Tables\Columns\TextColumn::make('value')->label('折扣值'),
                Tables\Columns\TextColumn::make('used_count')->label('已用'),
                Tables\Columns\TextColumn::make('max_uses')->label('上限'),
                Tables\Columns\IconColumn::make('is_active')->boolean()->label('啟用'),
                Tables\Columns\TextColumn::make('expires_at')->dateTime('Y-m-d')->label('到期日'),
            ])
            ->actions([
                \Filament\Actions\EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCoupons::route('/'),
            'create' => Pages\CreateCoupon::route('/create'),
            'edit' => Pages\EditCoupon::route('/{record}/edit'),
        ];
    }
}
