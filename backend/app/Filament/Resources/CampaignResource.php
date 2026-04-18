<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CampaignResource\Pages;
use App\Filament\Resources\CampaignResource\RelationManagers\BundlesRelationManager;
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
                \Filament\Schemas\Components\Section::make('活動資訊')
                    ->description('活動是「時間框」— 裡面可以放多個套組。每個套組有自己的名稱、圖片、詳情頁（/bundles/{代稱}）。')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->label('活動名稱')
                            ->placeholder('例：母親節活動'),
                        Forms\Components\TextInput::make('slug')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->label('網址代稱')
                            ->placeholder('mothers-day-2026')
                            ->helperText('活動頁網址：/campaigns/{代稱}'),
                        Forms\Components\Textarea::make('description')
                            ->label('活動說明')
                            ->rows(3)
                            ->columnSpanFull(),
                    ]),

                \Filament\Schemas\Components\Section::make('活動期間')->schema([
                    Forms\Components\DateTimePicker::make('start_at')
                        ->required()
                        ->label('開始時間'),
                    Forms\Components\DateTimePicker::make('end_at')
                        ->required()
                        ->after('start_at')
                        ->label('結束時間'),
                ])->columns(2),

                \Filament\Schemas\Components\Section::make('活動圖片')->schema([
                    Forms\Components\FileUpload::make('image')
                        ->image()
                        ->directory('campaigns')
                        ->disk('public')
                        ->label('活動主圖')
                        ->helperText('活動頁 hero 背景圖'),
                    Forms\Components\FileUpload::make('banner_image')
                        ->image()
                        ->directory('campaigns')
                        ->disk('public')
                        ->label('首頁倒數橫幅')
                        ->helperText('活動進行中出現在首頁'),
                ])->columns(2),

                \Filament\Schemas\Components\Section::make('設定')->schema([
                    Forms\Components\Toggle::make('is_active')
                        ->default(true)
                        ->label('啟用'),
                    Forms\Components\TextInput::make('sort_order')
                        ->numeric()
                        ->default(0)
                        ->label('排序'),
                ])->columns(2),

                \Filament\Schemas\Components\Section::make('套組管理')
                    ->description('活動建立後，儲存再回到編輯頁，即可在下方「套組」分頁新增、編輯個別套組。')
                    ->schema([])
                    ->visibleOn('edit'),
            ])
            ->columns(1);
    }

    public static function table(Table $table): Table
    {
        $statusOf = fn ($record): string => match (true) {
            $record->isRunning() => 'active',
            $record->hasEnded() => 'ended',
            $record->start_at > now() => 'upcoming',
            default => 'inactive',
        };
        $statusLabel = fn (string $state): string => match ($state) {
            'active' => '進行中',
            'upcoming' => '即將開始',
            'ended' => '已結束',
            default => '未啟用',
        };
        $statusColor = fn (string $state): string => match ($state) {
            'active' => 'success',
            'upcoming' => 'warning',
            'ended' => 'danger',
            default => 'gray',
        };

        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('image')
                    ->square()
                    ->size(56)
                    ->disk('public')
                    ->label('主圖'),

                Tables\Columns\TextColumn::make('name')
                    ->weight('bold')
                    ->searchable()
                    ->sortable()
                    ->description(fn ($record) => $record->slug)
                    ->label('活動名稱'),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->getStateUsing($statusOf)
                    ->color($statusColor)
                    ->formatStateUsing($statusLabel)
                    ->label('狀態'),

                Tables\Columns\TextColumn::make('start_at')
                    ->dateTime('Y-m-d H:i')
                    ->sortable()
                    ->label('開始'),

                Tables\Columns\TextColumn::make('end_at')
                    ->dateTime('Y-m-d H:i')
                    ->sortable()
                    ->label('結束'),

                Tables\Columns\TextColumn::make('bundles_count')
                    ->counts('bundles')
                    ->alignEnd()
                    ->label('套組數'),

                Tables\Columns\ToggleColumn::make('is_active')
                    ->label('啟用'),
            ])
            ->paginated([25, 50, 100])
            ->defaultPaginationPageOption(25)
            ->defaultSort('start_at', 'desc')
            ->filters([
                Tables\Filters\Filter::make('active')
                    ->label('進行中')
                    ->query(fn ($q) => $q->active()),
                Tables\Filters\TernaryFilter::make('is_active')->label('啟用狀態'),
            ])
            ->recordUrl(fn ($record) => Pages\EditCampaign::getUrl(['record' => $record]))
            ->actions([
                \Filament\Actions\EditAction::make()->iconButton(),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            BundlesRelationManager::class,
        ];
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
