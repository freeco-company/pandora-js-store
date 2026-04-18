<?php

namespace App\Filament\Resources\CampaignResource\RelationManagers;

use App\Models\Product;
use Filament\Forms;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

/**
 * 套組管理 — 每個 Campaign 下可有多個 Bundle (套組)。
 * 每個套組獨立命名、配圖、買/送商品、對應 /bundles/{slug} 詳情頁。
 */
class BundlesRelationManager extends RelationManager
{
    protected static string $relationship = 'bundles';

    protected static ?string $title = '套組';

    public function form(Schema $form): Schema
    {
        return $form
            ->schema([
                \Filament\Schemas\Components\Section::make('套組資訊')->schema([
                    Forms\Components\TextInput::make('name')
                        ->required()
                        ->label('套組名稱')
                        ->placeholder('例：益生菌買3送1'),
                    Forms\Components\TextInput::make('slug')
                        ->required()
                        ->unique(ignoreRecord: true)
                        ->label('網址代稱')
                        ->placeholder('mothers-day-probio-bundle')
                        ->helperText('套組頁網址：/bundles/{代稱}'),
                    Forms\Components\Textarea::make('description')
                        ->rows(2)
                        ->label('套組說明')
                        ->placeholder('買三送一 · 黃金比例益生菌 · 母親節限定')
                        ->columnSpanFull(),
                    Forms\Components\FileUpload::make('image')
                        ->image()
                        ->directory('bundles')
                        ->disk('public')
                        ->label('套組主圖')
                        ->helperText('活動頁卡片 + 套組詳情頁 hero')
                        ->columnSpanFull(),
                    Forms\Components\TextInput::make('sort_order')
                        ->numeric()
                        ->default(0)
                        ->label('排序'),
                ]),

                \Filament\Schemas\Components\Section::make('購買商品（計價）')
                    ->description('套組價 = 這區商品 VIP 價 × 數量。例：3 × 益生菌。')
                    ->schema([
                        Forms\Components\Repeater::make('buy_items_ui')
                            ->hiddenLabel()
                            ->schema([
                                Forms\Components\Select::make('product_id')
                                    ->label('商品')
                                    ->options(fn () => Product::where('is_active', true)->orderBy('name')->pluck('name', 'id'))
                                    ->searchable()
                                    ->required(),
                                Forms\Components\TextInput::make('quantity')
                                    ->label('數量')
                                    ->numeric()
                                    ->minValue(1)
                                    ->default(1)
                                    ->required(),
                            ])
                            ->columns(2)
                            ->addActionLabel('+ 加入購買商品')
                            ->reorderable(false)
                            ->minItems(1)
                            ->dehydrated(),
                    ]),

                \Filament\Schemas\Components\Section::make('贈送商品（免費）')
                    ->description('送給顧客的贈品，不計入套組價。可以跟購買商品同 SKU（買3送1）。')
                    ->schema([
                        Forms\Components\Repeater::make('gift_items_ui')
                            ->hiddenLabel()
                            ->schema([
                                Forms\Components\Select::make('product_id')
                                    ->label('商品')
                                    ->options(fn () => Product::where('is_active', true)->orderBy('name')->pluck('name', 'id'))
                                    ->searchable()
                                    ->required(),
                                Forms\Components\TextInput::make('quantity')
                                    ->label('數量')
                                    ->numeric()
                                    ->minValue(1)
                                    ->default(1)
                                    ->required(),
                            ])
                            ->columns(2)
                            ->addActionLabel('+ 加入贈送商品')
                            ->reorderable(false)
                            ->dehydrated(),
                    ]),
            ])
            ->columns(1);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                Tables\Columns\ImageColumn::make('image')
                    ->square()->size(56)->disk('public')->label('主圖'),
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->weight('bold')
                    ->description(fn ($record) => $record->slug)
                    ->label('套組名稱'),
                Tables\Columns\TextColumn::make('buy_items_count')
                    ->counts('buyItems')
                    ->alignEnd()
                    ->label('買 (種類)'),
                Tables\Columns\TextColumn::make('gift_items_count')
                    ->counts('giftItems')
                    ->alignEnd()
                    ->label('送 (種類)'),
                Tables\Columns\TextColumn::make('sort_order')
                    ->sortable()
                    ->alignEnd()
                    ->label('排序'),
            ])
            ->defaultSort('sort_order')
            ->headerActions([
                \Filament\Actions\CreateAction::make()
                    ->mutateFormDataUsing(fn (array $data): array => self::stripPivotState($data))
                    ->after(function ($record, array $data) {
                        self::syncBundlePivot($record, $data['buy_items_ui'] ?? [], $data['gift_items_ui'] ?? []);
                    }),
            ])
            ->actions([
                \Filament\Actions\EditAction::make()
                    ->mutateRecordDataUsing(fn (array $data, $record): array => self::hydratePivotState($data, $record))
                    ->mutateFormDataUsing(fn (array $data): array => self::stripPivotState($data))
                    ->after(function ($record, array $data) {
                        self::syncBundlePivot($record, $data['buy_items_ui'] ?? [], $data['gift_items_ui'] ?? []);
                    }),
                \Filament\Actions\DeleteAction::make(),
            ]);
    }

    private static function hydratePivotState(array $data, $record): array
    {
        $data['buy_items_ui'] = $record->buyItems->map(fn ($p) => [
            'product_id' => $p->id,
            'quantity' => (int) $p->pivot->quantity,
        ])->values()->toArray();
        $data['gift_items_ui'] = $record->giftItems->map(fn ($p) => [
            'product_id' => $p->id,
            'quantity' => (int) $p->pivot->quantity,
        ])->values()->toArray();
        return $data;
    }

    private static function stripPivotState(array $data): array
    {
        unset($data['buy_items_ui'], $data['gift_items_ui']);
        return $data;
    }

    private static function syncBundlePivot($bundle, array $buyItems, array $giftItems): void
    {
        $bundle->products()->detach();
        foreach ($buyItems as $row) {
            $pid = (int) ($row['product_id'] ?? 0);
            if (!$pid) continue;
            $bundle->products()->attach($pid, [
                'role' => 'buy',
                'quantity' => max(1, (int) ($row['quantity'] ?? 1)),
            ]);
        }
        foreach ($giftItems as $row) {
            $pid = (int) ($row['product_id'] ?? 0);
            if (!$pid) continue;
            $bundle->products()->attach($pid, [
                'role' => 'gift',
                'quantity' => max(1, (int) ($row['quantity'] ?? 1)),
            ]);
        }
    }
}
