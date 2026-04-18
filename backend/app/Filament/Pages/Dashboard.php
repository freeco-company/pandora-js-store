<?php

namespace App\Filament\Pages;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Pages\Dashboard\Concerns\HasFiltersForm;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class Dashboard extends BaseDashboard
{
    use HasFiltersForm;

    protected static ?string $title = '戰情室 Dashboard';

    public function filtersForm(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('期間篩選')
                    ->description('所有統計與圖表會依所選期間重新計算')
                    ->icon('heroicon-o-calendar-days')
                    ->schema([
                        Select::make('preset')
                            ->label('快速選擇')
                            ->options([
                                'today' => '今日',
                                'yesterday' => '昨日',
                                'this_week' => '本週',
                                'last_7' => '最近 7 天',
                                'last_30' => '最近 30 天',
                                'this_month' => '本月',
                                'last_month' => '上月',
                                'this_quarter' => '本季',
                                'this_year' => '本年',
                                'custom' => '自訂區間',
                            ])
                            ->default('last_30')
                            ->live()
                            ->afterStateUpdated(function ($state, callable $set) {
                                $range = static::resolvePreset($state);
                                if ($range) {
                                    $set('startDate', $range[0]->toDateString());
                                    $set('endDate', $range[1]->toDateString());
                                }
                            }),
                        DatePicker::make('startDate')
                            ->label('起始日期')
                            ->default(now()->subDays(29)->toDateString())
                            ->native(false)
                            ->displayFormat('Y/m/d'),
                        DatePicker::make('endDate')
                            ->label('結束日期')
                            ->default(now()->toDateString())
                            ->native(false)
                            ->displayFormat('Y/m/d')
                            ->afterOrEqual('startDate'),
                    ])
                    ->columns(['default' => 1, 'sm' => 3])
                    ->collapsible(),
            ]);
    }

    public static function resolvePreset(?string $preset): ?array
    {
        return match ($preset) {
            'today' => [now()->startOfDay(), now()->endOfDay()],
            'yesterday' => [now()->subDay()->startOfDay(), now()->subDay()->endOfDay()],
            'this_week' => [now()->startOfWeek(), now()->endOfDay()],
            'last_7' => [now()->subDays(6)->startOfDay(), now()->endOfDay()],
            'last_30' => [now()->subDays(29)->startOfDay(), now()->endOfDay()],
            'this_month' => [now()->startOfMonth(), now()->endOfDay()],
            'last_month' => [now()->subMonthNoOverflow()->startOfMonth(), now()->subMonthNoOverflow()->endOfMonth()],
            'this_quarter' => [now()->startOfQuarter(), now()->endOfDay()],
            'this_year' => [now()->startOfYear(), now()->endOfDay()],
            default => null,
        };
    }

    public function getColumns(): int | array
    {
        return [
            'default' => 1,
            'md' => 2,
            'xl' => 3,
        ];
    }
}
