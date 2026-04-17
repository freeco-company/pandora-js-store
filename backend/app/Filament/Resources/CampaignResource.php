<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CampaignResource\Pages;
use App\Models\Campaign;
use Filament\Forms;
use Filament\Schemas\Schema;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use BackedEnum;
use UnitEnum;

class CampaignResource extends Resource
{
    protected static ?string $model = Campaign::class;

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-fire';
    protected static string | UnitEnum | null $navigationGroup = '行銷管理';
    protected static ?string $navigationLabel = '活動管理';
    protected static ?string $modelLabel = '活動';
    protected static ?string $pluralModelLabel = '活動';
    protected static ?int $navigationSort = 1;

    public static function form(Schema $form): Schema
    {
        return $form
            ->schema([
                \Filament\Schemas\Components\Section::make('活動資訊')->schema([
                    Forms\Components\TextInput::make('name')
                        ->required()
                        ->label('活動名稱')
                        ->placeholder('例：2026 夏季感恩祭'),
                    Forms\Components\TextInput::make('slug')
                        ->required()
                        ->unique(ignoreRecord: true)
                        ->label('網址代稱')
                        ->placeholder('summer-2026')
                        ->helperText('活動頁 URL：/campaigns/{slug}'),
                    Forms\Components\Textarea::make('description')
                        ->label('活動說明')
                        ->rows(3),
                    Forms\Components\Toggle::make('is_active')
                        ->default(true)
                        ->label('啟用'),
                    Forms\Components\TextInput::make('sort_order')
                        ->numeric()
                        ->default(0)
                        ->label('排序'),
                ])->columns(2),

                \Filament\Schemas\Components\Section::make('活動期間')->schema([
                    Forms\Components\DateTimePicker::make('start_at')
                        ->required()
                        ->label('開始時間'),
                    Forms\Components\DateTimePicker::make('end_at')
                        ->required()
                        ->after('start_at')
                        ->label('結束時間'),
                ])->columns(2),

                \Filament\Schemas\Components\Section::make('圖片')->schema([
                    Forms\Components\FileUpload::make('image')
                        ->image()
                        ->directory('campaigns')
                        ->disk('public')
                        ->label('活動主圖'),
                    Forms\Components\FileUpload::make('banner_image')
                        ->image()
                        ->directory('campaigns')
                        ->disk('public')
                        ->label('首頁倒數橫幅')
                        ->helperText('活動進行中會出現在首頁的倒數計時區塊'),
                ])->columns(2),

                \Filament\Schemas\Components\Section::make('活動商品組（1–3 組）')->schema([
                    Forms\Components\Select::make('products')
                        ->relationship('products', 'name')
                        ->multiple()
                        ->preload()
                        ->searchable()
                        ->label('選擇活動商品')
                        ->helperText('加入的商品會在活動期間顯示在活動頁，活動結束後自動隱藏。'),
                ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\Layout\Stack::make([
                    Tables\Columns\Layout\Split::make([
                        Tables\Columns\TextColumn::make('name')
                            ->weight('bold')
                            ->size(\Filament\Support\Enums\TextSize::Large)
                            ->searchable()
                            ->label('名稱'),
                        Tables\Columns\BadgeColumn::make('status')
                            ->getStateUsing(fn ($record) => match (true) {
                                $record->isRunning() => 'active',
                                $record->hasEnded() => 'ended',
                                $record->start_at > now() => 'upcoming',
                                default => 'inactive',
                            })
                            ->colors([
                                'success' => 'active',
                                'warning' => 'upcoming',
                                'danger' => 'ended',
                                'gray' => 'inactive',
                            ])
                            ->formatStateUsing(fn ($state) => match ($state) {
                                'active' => '進行中',
                                'upcoming' => '即將開始',
                                'ended' => '已結束',
                                default => '未啟用',
                            })
                            ->grow(false)
                            ->label('狀態'),
                    ]),
                    Tables\Columns\Layout\Split::make([
                        Tables\Columns\TextColumn::make('start_at')
                            ->dateTime('m/d H:i')
                            ->icon('heroicon-m-play')
                            ->label('開始'),
                        Tables\Columns\TextColumn::make('end_at')
                            ->dateTime('m/d H:i')
                            ->icon('heroicon-m-stop')
                            ->label('結束'),
                    ]),
                    Tables\Columns\TextColumn::make('products_count')
                        ->counts('products')
                        ->icon('heroicon-m-cube')
                        ->formatStateUsing(fn ($state) => "{$state} 組商品")
                        ->label('商品'),
                ])->space(1),
            ])
            ->contentGrid([
                'default' => 1,
                'md' => 2,
            ])
            ->defaultSort('start_at', 'desc')
            ->filters([
                Tables\Filters\Filter::make('active')
                    ->label('進行中')
                    ->query(fn ($q) => $q->active()),
                Tables\Filters\Filter::make('upcoming')
                    ->label('即將開始')
                    ->query(fn ($q) => $q->upcoming()),
            ])
            ->recordUrl(fn ($record) => Pages\EditCampaign::getUrl(['record' => $record]));
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCampaigns::route('/'),
            'create' => Pages\CreateCampaign::route('/create'),
            'edit' => Pages\EditCampaign::route('/{record}/edit'),
        ];
    }
}
