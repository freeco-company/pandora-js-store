<?php

namespace App\Filament\Resources;

use App\Filament\Resources\OrderResource\Pages;
use App\Models\Order;
use Filament\Forms;
use Filament\Schemas\Schema;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use BackedEnum;
use UnitEnum;

class OrderResource extends Resource
{
    protected static ?string $model = Order::class;

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-clipboard-document-list';
    protected static string | UnitEnum | null $navigationGroup = '訂單管理';
    protected static ?string $navigationLabel = '訂單';
    protected static ?string $modelLabel = '訂單';
    protected static ?string $pluralModelLabel = '訂單';
    protected static ?int $navigationSort = 1;

    public static function form(Schema $form): Schema
    {
        return $form
            ->schema([
                \Filament\Schemas\Components\Section::make('訂單資訊')->schema([
                    Forms\Components\TextInput::make('order_number')
                        ->disabled()
                        ->label('訂單編號'),
                    Forms\Components\Select::make('status')
                        ->options([
                            'pending' => '待處理',
                            'processing' => '處理中',
                            'shipped' => '已出貨',
                            'completed' => '已完成',
                            'cancelled' => '已取消',
                            'refunded' => '已退款',
                            'cod_no_pickup' => '貨到付款未取件（自動加黑名單）',
                        ])
                        ->required()
                        ->helperText('將狀態改為「貨到付款未取件」時，系統會自動把客戶 email/phone 加入黑名單，永久停用其貨到付款。')
                        ->label('狀態'),
                    Forms\Components\Select::make('pricing_tier')
                        ->options([
                            'regular' => '原價',
                            'combo' => '1+1 搭配價',
                            'vip' => 'VIP 優惠價',
                        ])
                        ->disabled()
                        ->label('價格方案'),
                    Forms\Components\TextInput::make('total')
                        ->disabled()
                        ->prefix('$')
                        ->label('總金額'),
                ])->columns(2),

                \Filament\Schemas\Components\Section::make('��款資訊')->schema([
                    Forms\Components\Select::make('payment_method')
                        ->options([
                            'ecpay_credit' => '信用卡（綠界）',
                            'bank_transfer' => 'ATM 轉帳',
                            'cod' => '貨到付款',
                        ])
                        ->label('付款方式'),
                    Forms\Components\Select::make('payment_status')
                        ->options([
                            'unpaid' => '未付款',
                            'paid' => '已付款',
                            'refunded' => '已退款',
                        ])
                        ->label('付款狀態'),
                    Forms\Components\TextInput::make('ecpay_trade_no')
                        ->label('綠界���易編號'),
                ])->columns(2),

                \Filament\Schemas\Components\Section::make('配送資訊')->schema([
                    Forms\Components\Select::make('shipping_method')
                        ->options([
                            'home_delivery' => '宅配到府',
                            'cvs_711' => '7-11 超商取貨',
                            'cvs_family' => '全家超商取貨',
                        ])
                        ->label('配送方式'),
                    Forms\Components\TextInput::make('shipping_name')
                        ->label('收件人'),
                    Forms\Components\TextInput::make('shipping_phone')
                        ->label('��件電話'),
                    Forms\Components\TextInput::make('shipping_address')
                        ->label('配送地址'),
                    Forms\Components\TextInput::make('shipping_store_name')
                        ->label('門市名稱'),
                    Forms\Components\TextInput::make('shipping_store_id')
                        ->label('門市店號'),
                ])->columns(2),

                \Filament\Schemas\Components\Section::make('備註')->schema([
                    Forms\Components\Textarea::make('note')
                        ->label('訂單備註')
                        ->columnSpanFull(),
                ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('order_number')
                    ->searchable()
                    ->sortable()
                    ->label('訂單編號'),
                Tables\Columns\TextColumn::make('customer.name')
                    ->searchable()
                    ->label('客戶'),
                Tables\Columns\BadgeColumn::make('status')
                    ->colors([
                        'warning' => 'pending',
                        'primary' => fn ($state) => in_array($state, ['processing', 'shipped']),
                        'success' => 'completed',
                        'danger' => fn ($state) => in_array($state, ['cancelled', 'refunded', 'cod_no_pickup']),
                    ])
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'pending' => '待處理',
                        'processing' => '處理中',
                        'shipped' => '已出貨',
                        'completed' => '已完成',
                        'cancelled' => '已取消',
                        'refunded' => '已退款',
                        'cod_no_pickup' => '貨到付款未取件',
                        default => $state,
                    })
                    ->label('狀態'),
                Tables\Columns\TextColumn::make('pricing_tier')
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'regular' => '原價',
                        'combo' => '搭配價',
                        'vip' => 'VIP',
                        default => $state,
                    })
                    ->label('方案'),
                Tables\Columns\TextColumn::make('total')
                    ->money('TWD')
                    ->sortable()
                    ->label('金額'),
                Tables\Columns\TextColumn::make('payment_method')
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'ecpay_credit' => '信用卡',
                        'bank_transfer' => 'ATM',
                        'cod' => '貨到付款',
                        default => $state ?? '-',
                    })
                    ->label('付款'),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime('Y-m-d H:i')
                    ->sortable()
                    ->label('建立時間'),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'pending' => '待處理',
                        'processing' => '處理中',
                        'completed' => '已完成',
                        'cancelled' => '已取消',
                        'refunded' => '已退款',
                    ])
                    ->label('狀態'),
                Tables\Filters\SelectFilter::make('payment_method')
                    ->options([
                        'ecpay_credit' => '信用卡',
                        'bank_transfer' => 'ATM',
                        'cod' => '貨到付款',
                    ])
                    ->label('付款方式'),
            ])
            ->actions([
                \Filament\Actions\EditAction::make(),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListOrders::route('/'),
            'edit' => Pages\EditOrder::route('/{record}/edit'),
        ];
    }
}
