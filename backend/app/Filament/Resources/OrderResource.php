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
                            'pending_confirmation' => '待客戶 LINE 確認（COD）',
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
                            // Legacy WP imports — keep so the form doesn't nuke the value on save
                            'Wooecpay_Gateway_Credit' => '信用卡（舊 WP 綠界）',
                            'ry_newebpay_credit' => '藍新信用卡（舊 WP）',
                            'ry_newebpay_credit_installment' => '藍新分期（舊 WP）',
                            'bacs' => 'ATM 轉帳（舊 WP）',
                        ])
                        ->label('付款方式'),
                    Forms\Components\Select::make('payment_status')
                        ->options([
                            'unpaid' => '未付款',
                            'pending' => '未付款（舊 WP 資料）',
                            'paid' => '已付款',
                            'refunded' => '已退款',
                            'failed' => '付款失敗',
                        ])
                        ->label('付款狀態'),
                    Forms\Components\TextInput::make('ecpay_trade_no')
                        ->label('綠界交易編號')
                        ->helperText('綠界金流的 TradeNo，付款成功後由 API 自動回填。API 失敗或需修正時可手動編輯。'),
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
                        ->label('綠界物流單號 (AllPayLogisticsID)')
                        ->helperText('付款後系統自動向綠界建立；卡在 [300] 時可去綠界廠商後台查詢後手動貼入。'),
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
                        ->columnSpanFull(),
                    Forms\Components\DateTimePicker::make('logistics_created_at')
                        ->label('物流建立時間'),
                    \Filament\Schemas\Components\Actions::make([
                        \Filament\Actions\Action::make('query_other_fields')
                            ->label('向綠界查詢其他欄位')
                            ->icon('heroicon-o-arrow-path')
                            ->color('info')
                            ->visible(fn ($record) => (bool) $record?->ecpay_logistics_id)
                            ->requiresConfirmation()
                            ->modalHeading('用目前的物流單號向綠界查詢其他欄位？')
                            ->modalDescription('會呼叫綠界 /Helper/QueryLogisticsInfo，自動補寫「寄件編號 / 代收款編號 / 驗證碼 / 狀態訊息」。')
                            ->action(function ($record) {
                                try {
                                    $data = app(\App\Services\EcpayLogisticsService::class)
                                        ->queryByLogisticsId($record);
                                    \Filament\Notifications\Notification::make()
                                        ->title('查詢成功 ✓')
                                        ->body(sprintf('寄件編號：%s', $data['BookingNote'] ?? '（無）'))
                                        ->success()
                                        ->send();
                                } catch (\Throwable $e) {
                                    \Filament\Notifications\Notification::make()
                                        ->title('查詢失敗')
                                        ->body($e->getMessage())
                                        ->danger()
                                        ->persistent()
                                        ->send();
                                }
                            }),
                        \Filament\Actions\Action::make('clear_logistics')
                            ->label('清除物流單（重新建立用）')
                            ->icon('heroicon-o-trash')
                            ->color('danger')
                            ->requiresConfirmation()
                            ->modalHeading('確定清除物流單資料？')
                            ->modalDescription('清除後可重新點擊「建立物流單」按鈕建立新的物流單。')
                            ->visible(fn ($record) => (bool) $record?->ecpay_logistics_id)
                            ->action(function ($record) {
                                $record->update([
                                    'ecpay_logistics_id' => null,
                                    'cvs_payment_no' => null,
                                    'cvs_validation_no' => null,
                                    'booking_note' => null,
                                    'logistics_status_msg' => null,
                                    'logistics_created_at' => null,
                                ]);
                                \Filament\Notifications\Notification::make()
                                    ->title('物流單已清除')
                                    ->body('可回到訂單列表點擊「建立物流單」重新建立。')
                                    ->success()
                                    ->send();
                            }),
                    ])->columnSpanFull(),
                ])->columns(2)->collapsible()->collapsed(fn ($record) => ! $record?->shipping_method || ! str_starts_with($record->shipping_method, 'cvs_')),

                \Filament\Schemas\Components\Section::make('備註')->schema([
                    Forms\Components\Textarea::make('note')
                        ->label('訂單備註')
                        ->columnSpanFull(),
                ])->collapsible(),
            ])
            ->columns(1); // Top-level: stack sections vertically (no side-by-side)
    }

    public static function table(Table $table): Table
    {
        $statusOptions = [
            'pending' => '待處理',
            'pending_confirmation' => '待 LINE 確認',
            'processing' => '處理中',
            'shipped' => '已出貨',
            'completed' => '已完成',
            'cancelled' => '已取消',
            'refunded' => '已退款',
            'cod_no_pickup' => '未取件',
        ];

        return $table
            ->stackedOnMobile()
            // Preload items_count so the "商品" action tooltip doesn't fire
            // N+1 queries when hovering down a long order list.
            ->modifyQueryUsing(fn ($query) => $query->withCount('items'))
            ->columns([
                Tables\Columns\TextColumn::make('order_number')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->weight('bold')
                    ->label('訂單編號'),

                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime('m/d H:i')
                    ->sortable()
                    ->tooltip(fn ($record) => $record->created_at?->format('Y-m-d H:i:s'))
                    ->label('建立時間'),

                Tables\Columns\TextColumn::make('shipping_name')
                    ->searchable()
                    ->description(fn ($record) => $record->shipping_phone)
                    ->label('收件人'),

                Tables\Columns\TextColumn::make('shipping_method')
                    ->badge()
                    ->formatStateUsing(fn (?string $state) => match ($state) {
                        'home_delivery' => '宅配',
                        'cvs_711' => '7-11',
                        'cvs_family' => '全家',
                        default => $state ?? '-',
                    })
                    ->color(fn (?string $state) => match ($state) {
                        'home_delivery' => 'info',
                        'cvs_711', 'cvs_family' => 'warning',
                        default => 'gray',
                    })
                    ->label('配送方式'),

                Tables\Columns\TextColumn::make('ecpay_logistics_id')
                    ->label('綠界物流單號')
                    ->placeholder('—')
                    ->copyable()
                    ->description(fn ($record) => $record->booking_note ? "寄件 {$record->booking_note}" : null),

                Tables\Columns\TextColumn::make('ecpay_trade_no')
                    ->label('綠界交易編號')
                    ->placeholder('—')
                    ->copyable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('referer_source')
                    ->label('來源')
                    ->badge()
                    ->placeholder('—')
                    ->formatStateUsing(fn (?string $state) => match ($state) {
                        'direct' => '直接',
                        'google' => 'Google',
                        'google_ads' => 'Google Ads',
                        'bing' => 'Bing',
                        'bing_ads' => 'Bing Ads',
                        'yahoo' => 'Yahoo',
                        'facebook' => 'Facebook',
                        'facebook_ads' => 'Meta Ads',
                        'instagram' => 'Instagram',
                        'line' => 'LINE',
                        'tiktok_ads' => 'TikTok Ads',
                        'linkedin_ads' => 'LinkedIn Ads',
                        'other_ads' => '其他廣告',
                        'email' => 'Email',
                        'ai_referral' => 'AI 引薦',
                        'other' => '其他',
                        default => $state ?? '—',
                    })
                    ->color(fn (?string $state) => match ($state) {
                        'direct' => 'success',
                        'google', 'bing', 'yahoo' => 'info',
                        'google_ads', 'facebook_ads', 'bing_ads', 'tiktok_ads', 'linkedin_ads', 'other_ads' => 'warning',
                        'facebook', 'instagram', 'line' => 'primary',
                        'ai_referral' => 'success',
                        default => 'gray',
                    })
                    ->description(fn ($record) => $record->utm_campaign ? "活動：{$record->utm_campaign}" : null)
                    ->tooltip(fn ($record) => $record->landing_path ? "落地頁：{$record->landing_path}" : null),

                Tables\Columns\TextColumn::make('payment_method')
                    ->badge()
                    ->formatStateUsing(fn (?string $state) => match ($state) {
                        // Current system values
                        'ecpay_credit' => '信用卡',
                        'bank_transfer' => 'ATM',
                        'cod' => '貨到付款',
                        // Legacy values carried over from the WP / WooCommerce import
                        'Wooecpay_Gateway_Credit' => '信用卡（舊）',
                        'ry_newebpay_credit' => '藍新信用卡（舊）',
                        'ry_newebpay_credit_installment' => '藍新分期（舊）',
                        'bacs' => 'ATM（舊）',
                        default => $state ?? '—',
                    })
                    ->color(fn (?string $state) => match ($state) {
                        'ecpay_credit', 'Wooecpay_Gateway_Credit', 'ry_newebpay_credit', 'ry_newebpay_credit_installment' => 'primary',
                        'bank_transfer', 'bacs' => 'info',
                        'cod' => 'warning',
                        default => 'gray',
                    })
                    ->label('付款方式'),

                Tables\Columns\TextColumn::make('payment_status')
                    ->badge()
                    ->formatStateUsing(fn (?string $state) => match ($state) {
                        'paid' => '已付款',
                        'unpaid', 'pending' => '未付款', // 'pending' from WP imports
                        'refunded' => '已退款',
                        'failed' => '付款失敗',
                        default => $state ?? '—',
                    })
                    ->color(fn (?string $state) => match ($state) {
                        'paid' => 'success',
                        'unpaid', 'pending' => 'warning',
                        'failed' => 'danger',
                        'refunded' => 'gray',
                        default => 'gray',
                    })
                    ->label('付款狀態'),

                Tables\Columns\TextColumn::make('total')
                    ->money('TWD', 0)
                    ->sortable()
                    ->alignEnd()
                    ->weight('bold')
                    ->color('primary')
                    ->label('金額'),

                Tables\Columns\SelectColumn::make('status')
                    ->options($statusOptions)
                    ->selectablePlaceholder(false)
                    ->rules(['required'])
                    ->label('狀態'),
            ])
            ->paginated([25, 50, 100, 200])
            ->defaultPaginationPageOption(25)
            ->defaultSort('created_at', 'desc')
            ->recordUrl(fn ($record) => Pages\EditOrder::getUrl(['record' => $record]))
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options($statusOptions)
                    ->label('狀態'),
                Tables\Filters\SelectFilter::make('payment_method')
                    ->options([
                        'ecpay_credit' => '信用卡',
                        'bank_transfer' => 'ATM',
                        'cod' => '貨到付款',
                        'Wooecpay_Gateway_Credit' => '信用卡（舊 WP）',
                        'ry_newebpay_credit' => '藍新信用卡（舊）',
                        'ry_newebpay_credit_installment' => '藍新分期（舊）',
                        'bacs' => 'ATM（舊 WP）',
                    ])
                    ->label('付款方式'),
                Tables\Filters\SelectFilter::make('payment_status')
                    ->options([
                        'unpaid' => '未付款',
                        'pending' => '未付款（舊）',
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
                Tables\Filters\SelectFilter::make('referer_source')
                    ->options([
                        'direct' => '直接',
                        'instagram' => 'Instagram',
                        'line' => 'LINE',
                        'facebook' => 'Facebook',
                        'google' => 'Google',
                        'facebook_ads' => 'Meta Ads',
                        'google_ads' => 'Google Ads',
                        'email' => 'Email',
                        'ai_referral' => 'AI 引薦',
                        'other' => '其他',
                    ])
                    ->label('來源'),
            ])
            ->actions([
                // Inline item preview — clicking opens a modal with all line
                // items (thumbnail + name + qty + subtotal). Faster than
                // navigating into /edit just to see what was ordered.
                \Filament\Actions\Action::make('items')
                    ->label('商品')
                    ->icon('heroicon-o-cube')
                    ->color('gray')
                    ->iconButton()
                    ->tooltip(fn ($record) => "共 {$record->items_count} 項商品")
                    ->modalHeading(fn ($record) => "訂單 {$record->order_number} · 商品明細")
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('關閉')
                    ->modalContent(fn ($record) => view('filament.order-items-modal', [
                        'items' => $record->items()->with('product')->get(),
                        // Map bundle display name → Bundle row so the modal
                        // can show the real bundle cover art (not a
                        // constituent product's image).
                        'bundles' => \App\Models\Bundle::whereIn('name', $record
                            ->items()
                            ->pluck('product_name')
                            ->map(fn ($n) => preg_match('/^【([^｜】]+?)(?:｜贈品)?】/u', (string) $n, $m) ? $m[1] : null)
                            ->filter()
                            ->unique()
                            ->values())
                            ->get()
                            ->keyBy('name'),
                        'order' => $record,
                    ])),

                \Filament\Actions\Action::make('create_logistics')
                    ->label('建立物流單')
                    ->icon('heroicon-o-building-storefront')
                    ->color('warning')
                    ->visible(fn ($record) => in_array($record->shipping_method, ['cvs_711', 'cvs_family'])
                        && ! $record->ecpay_logistics_id
                        // Non-COD must be paid before we ask ECPay to create the shipment.
                        && ($record->payment_method === 'cod' || $record->payment_status === 'paid')
                        // Hide while previous 300 「處理中」 is awaiting ECPay callback.
                        && ! ($record->logistics_created_at && str_starts_with((string) $record->logistics_status_msg, '[300]')))
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
                \Filament\Actions\EditAction::make()->iconButton(),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            OrderResource\RelationManagers\ItemsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListOrders::route('/'),
            'edit' => Pages\EditOrder::route('/{record}/edit'),
        ];
    }
}
