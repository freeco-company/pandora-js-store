<?php

namespace App\Filament\Resources;

use App\Filament\Resources\FranchiseConsultationResource\Pages;
use App\Models\FranchiseConsultation;
use BackedEnum;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use UnitEnum;

/**
 * 加盟諮詢 inbox — public 表單 → DB → 業務人工聯繫流程的後台。
 *
 * 資料源：[FranchiseConsultationController](../../Http/Controllers/Api/FranchiseConsultationController.php)
 * 寫進來。Admin 不該手動 create / 隨便改聯絡資料；只改 status / admin_note /
 * contacted_at 等業務追蹤欄位。
 */
class FranchiseConsultationResource extends Resource
{
    protected static ?string $model = FranchiseConsultation::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-inbox';

    protected static string|UnitEnum|null $navigationGroup = '訂單管理';

    protected static ?string $navigationLabel = '加盟諮詢';

    protected static ?string $modelLabel = '加盟諮詢';

    protected static ?string $pluralModelLabel = '加盟諮詢';

    protected static ?int $navigationSort = 5;

    public static function form(Schema $form): Schema
    {
        return $form
            ->schema([
                \Filament\Schemas\Components\Section::make('來件資料（唯讀）')
                    ->description('由公開表單寫入，請勿改動。如果要修聯絡方式，請新建一筆。')
                    ->schema([
                        Forms\Components\TextInput::make('name')->label('姓名')->disabled(),
                        Forms\Components\TextInput::make('phone')->label('電話')->disabled(),
                        Forms\Components\TextInput::make('email')->label('Email')->disabled(),
                        Forms\Components\TextInput::make('source')->label('來源')->disabled(),
                        Forms\Components\TextInput::make('pandora_user_uuid')->label('Pandora UUID')->disabled(),
                        Forms\Components\Textarea::make('note')->label('客戶留言')->disabled()->rows(3),
                    ])->columns(2),

                \Filament\Schemas\Components\Section::make('業務追蹤')
                    ->schema([
                        Forms\Components\Select::make('status')
                            ->label('狀態')
                            ->options([
                                'new' => 'new — 新進',
                                'contacted' => 'contacted — 已聯繫',
                                'qualified' => 'qualified — 合格 lead',
                                'closed' => 'closed — 已結案',
                            ])
                            ->required(),
                        Forms\Components\DateTimePicker::make('contacted_at')
                            ->label('聯繫時間')
                            ->seconds(false),
                        Forms\Components\Textarea::make('admin_note')
                            ->label('業務備註（內部）')
                            ->rows(4)
                            ->columnSpanFull(),
                    ])->columns(2),
            ])
            ->columns(1);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->stackedOnMobile()
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('姓名')
                    ->weight('bold')
                    ->searchable(),
                Tables\Columns\TextColumn::make('phone')
                    ->label('電話')
                    ->searchable()
                    ->copyable(),
                Tables\Columns\TextColumn::make('email')
                    ->label('Email')
                    ->placeholder('—')
                    ->searchable()
                    ->copyable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('source')
                    ->label('來源')
                    ->badge()
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('status')
                    ->label('狀態')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'new' => 'gray',
                        'contacted' => 'warning',
                        'qualified' => 'success',
                        'closed' => 'secondary',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('pandora_user_uuid')
                    ->label('UUID')
                    ->limit(8)
                    ->tooltip(fn (FranchiseConsultation $r) => $r->pandora_user_uuid)
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('contacted_at')
                    ->label('聯繫時間')
                    ->dateTime('Y-m-d H:i')
                    ->placeholder('未聯繫')
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('進件時間')
                    ->dateTime('Y-m-d H:i')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('狀態')
                    ->options([
                        'new' => 'new',
                        'contacted' => 'contacted',
                        'qualified' => 'qualified',
                        'closed' => 'closed',
                    ]),
                Tables\Filters\Filter::make('today')
                    ->label('今天')
                    ->query(fn ($q) => $q->whereDate('created_at', today())),
                Tables\Filters\Filter::make('last_7_days')
                    ->label('最近 7 天')
                    ->query(fn ($q) => $q->where('created_at', '>=', now()->subDays(7))),
                Tables\Filters\Filter::make('uncontacted')
                    ->label('待聯繫（new + 進件 > 1h）')
                    ->query(fn ($q) => $q
                        ->where('status', 'new')
                        ->where('created_at', '<=', now()->subHour())),
            ])
            ->recordUrl(fn ($record) => Pages\EditFranchiseConsultation::getUrl(['record' => $record]))
            ->actions([
                \Filament\Actions\Action::make('mark_contacted')
                    ->label('標已聯繫')
                    ->icon('heroicon-o-check')
                    ->color('warning')
                    ->visible(fn (FranchiseConsultation $r) => $r->status === 'new')
                    ->action(function (FranchiseConsultation $r): void {
                        $r->update([
                            'status' => 'contacted',
                            'contacted_at' => $r->contacted_at ?? now(),
                        ]);
                    }),
                \Filament\Actions\EditAction::make()->iconButton(),
            ])
            ->paginated([25, 50, 100, 200])
            ->defaultPaginationPageOption(25);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListFranchiseConsultations::route('/'),
            'edit' => Pages\EditFranchiseConsultation::route('/{record}/edit'),
        ];
    }
}
