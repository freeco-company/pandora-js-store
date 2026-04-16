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

                \Filament\Schemas\Components\Section::make('付款資訊')->schema([
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
                            'failed' => '付款失敗',
                        ])
                        ->label('付款狀態'),
                    Forms\Components\TextInput::make('ecpay_trade_no')
                        ->label('綠界交易編號')
                        ->helperText('綠界金流的 TradeNo，付款成功後自動回填'),
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
                        ->label('收件電話'),
                    Forms\Components\TextInput::make('shipping_address')
                        ->label('配送地址')
                        ->columnSpanFull(),
                    Forms\Components\TextInput::make('shipping_store_name')
                        ->label('門市名稱'),
                    Forms\Components\TextInput::make('shipping_store_id')
                        ->label('門市店號'),
                ])->columns(2),

                \Filament\Schemas\Components\Section::make('綠界物流（CVS）')->schema([
                    Forms\Components\TextInput::make('ecpay_logistics_id')
                        ->label('綠界物流編號 (AllPayLogisticsID)')
                        ->helperText('CVS 訂單付款後由系統自動向綠界建立，若尚未產生可手動補填。'),
                    Forms\Components\TextInput::make('booking_note')
                        ->label('寄件編號')
                        ->helperText('交件時出示給超商店員使用。'),
                    Forms\Components\TextInput::make('cvs_payment_no')
                        ->label('代收款編號')
                        ->helperText('僅貨到付款訂單會有。'),
                    Forms\Components\TextInput::make('cvs_validation_no')
                        ->label('驗證碼'),
                    Forms\Components\TextInput::make('logistics_status_msg')
                        ->label('綠界回傳訊息')
                        ->columnSpanFull()
                        ->disabled(),
                    Forms\Components\DateTimePicker::make('logistics_created_at')
                        ->label('物流建立時間')
                        ->disabled(),
                ])->columns(2)->collapsible()->collapsed(fn ($record) => ! $record?->shipping_method || ! str_starts_with($record->shipping_method, 'cvs_')),

                \Filament\Schemas\Components\Section::make('備註')->schema([
                    Forms\Components\Textarea::make('note')
                        ->label('訂單備註')
                        ->columnSpanFull(),
                ])->collapsible(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                // Card layout — responsive: 1 col on mobile, 2 on tablet, 3 on
                // desktop. Ends the painful horizontal-scroll-table UX on phone
                // and makes order triage possible with one thumb.
                Tables\Columns\Layout\Stack::make([
                    Tables\Columns\Layout\Split::make([
                        Tables\Columns\TextColumn::make('order_number')
                            ->searchable()
                            ->sortable()
                            ->copyable()
                            ->weight('bold')
                            ->size(\Filament\Support\Enums\TextSize::Large)
                            ->label('訂單編號'),
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
                                'cod_no_pickup' => '未取件',
                                default => $state,
                            })
                            ->grow(false)
                            ->label('狀態'),
                    ]),

                    Tables\Columns\TextColumn::make('customer.name')
                        ->searchable()
                        ->icon('heroicon-m-user')
                        ->description(fn ($record) => $record->customer?->email)
                        ->label('客戶'),

                    Tables\Columns\Layout\Split::make([
                        Tables\Columns\TextColumn::make('shipping_method')
                            ->formatStateUsing(fn (?string $state): string => match ($state) {
                                'home_delivery' => '🏠 宅配',
                                'cvs_711' => '🏪 7-11',
                                'cvs_family' => '🏪 全家',
                                default => $state ?? '-',
                            })
                            ->description(fn ($record) => $record->shipping_store_name ?: $record->shipping_address)
                            ->label('配送'),
                        Tables\Columns\TextColumn::make('payment_method')
                            ->formatStateUsing(fn (?string $state): string => match ($state) {
                                'ecpay_credit' => '💳 信用卡',
                                'bank_transfer' => '🏦 ATM',
                                'cod' => '📦 貨到付款',
                                default => $state ?? '-',
                            })
                            ->description(fn ($record) => match ($record->payment_status) {
                                'paid' => '✓ 已付款',
                                'unpaid' => '⏳ 未付款',
                                'refunded' => '↺ 已退款',
                                'failed' => '✕ 付款失敗',
                                default => null,
                            })
                            ->grow(false)
                            ->label('付款'),
                    ]),

                    Tables\Columns\Layout\Split::make([
                        Tables\Columns\TextColumn::make('total')
                            ->money('TWD')
                            ->sortable()
                            ->weight('bold')
                            ->color('primary')
                            ->size(\Filament\Support\Enums\TextSize::Large)
                            ->label('金額'),
                        Tables\Columns\TextColumn::make('created_at')
                            ->since()
                            ->tooltip(fn ($record) => $record->created_at?->format('Y-m-d H:i'))
                            ->icon('heroicon-m-clock')
                            ->grow(false)
                            ->label('建立'),
                    ]),

                    Tables\Columns\TextColumn::make('ecpay_logistics_id')
                        ->placeholder('— 未建立物流單 —')
                        ->copyable()
                        ->description(fn ($record) => $record->booking_note ? "寄件編號 {$record->booking_note}" : null)
                        ->icon('heroicon-m-building-storefront')
                        ->label('物流'),
                ])->space(2),
            ])
            ->contentGrid([
                'default' => 1,
                'md' => 2,
                'xl' => 3,
            ])
            ->paginated([12, 24, 48, 96])
            ->defaultPaginationPageOption(24)
            ->defaultSort('created_at', 'desc')
            ->recordUrl(fn ($record) => Pages\EditOrder::getUrl(['record' => $record]))
            ->actions([
                \Filament\Actions\Action::make('create_logistics')
                    ->label('建立物流單')
                    ->icon('heroicon-o-building-storefront')
                    ->color('warning')
                    ->visible(fn ($record) => in_array($record->shipping_method, ['cvs_711', 'cvs_family'])
                        && ! $record->ecpay_logistics_id
                        && $record->payment_status === 'paid')
                    ->requiresConfirmation()
                    ->modalHeading('向綠界建立超商物流單？')
                    ->modalDescription(fn ($record) => "訂單 {$record->order_number}（{$record->total}）將送往綠界 Express/Create，成功後自動回填物流編號與寄件編號。")
                    ->action(function ($record) {
                        try {
                            app(\App\Services\EcpayLogisticsService::class)->createCvsShipment($record);
                            \Filament\Notifications\Notification::make()
                                ->title('已建立物流單 ✓')
                                ->body("物流編號 {$record->fresh()->ecpay_logistics_id}")
                                ->success()
                                ->send();
                        } catch (\Throwable $e) {
                            \Filament\Notifications\Notification::make()
                                ->title('建立物流單失敗')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'pending' => '待處理',
                        'processing' => '處理中',
                        'shipped' => '已出貨',
                        'completed' => '已完成',
                        'cancelled' => '已取消',
                        'refunded' => '已退款',
                        'cod_no_pickup' => '未取件',
                    ])
                    ->label('狀態'),
                Tables\Filters\SelectFilter::make('payment_method')
                    ->options([
                        'ecpay_credit' => '信用卡',
                        'bank_transfer' => 'ATM',
                        'cod' => '貨到付款',
                    ])
                    ->label('付款方式'),
                Tables\Filters\SelectFilter::make('payment_status')
                    ->options([
                        'unpaid' => '未付款',
                        'paid' => '已付款',
                        'refunded' => '已退款',
                        'failed' => '付款失敗',
                    ])
                    ->label('付款狀態'),
                Tables\Filters\SelectFilter::make('shipping_method')
                    ->options([
                        'home_delivery' => '宅配',
                        'cvs_711' => '7-11',
                        'cvs_family' => '全家',
                    ])
                    ->label('配送方式'),
                Tables\Filters\Filter::make('needs_logistics')
                    ->label('待建立物流')
                    ->query(fn ($query) => $query
                        ->whereIn('shipping_method', ['cvs_711', 'cvs_family'])
                        ->whereNull('ecpay_logistics_id')
                        ->where('payment_status', 'paid')),
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
