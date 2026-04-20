<?php

namespace App\Filament\Widgets;

use App\Models\DiscordNotification;
use Carbon\Carbon;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Discord webhook activity for the selected dashboard period. Counts per
 * channel (orders / ads / compliance / ads_strategy) and surfaces failed
 * deliveries. Hidden if no notifications have ever been logged — keeps
 * the dashboard clean on fresh installs.
 */
class DiscordActivityWidget extends BaseWidget
{
    use InteractsWithPageFilters;

    protected static ?int $sort = 11;

    protected int | string | array $columnSpan = 'full';

    protected function getColumns(): int | array
    {
        return [
            'default' => 1,
            'sm' => 2,
            'xl' => 5,
        ];
    }

    public static function canView(): bool
    {
        return DiscordNotification::query()->exists();
    }

    protected function getStats(): array
    {
        [$start, $end] = $this->resolveRange();

        $base = DiscordNotification::whereBetween('sent_at', [$start, $end]);

        $total = (clone $base)->count();
        $failed = (clone $base)->where('success', false)->count();

        $byChannel = (clone $base)
            ->selectRaw('channel, COUNT(*) as c')
            ->groupBy('channel')
            ->pluck('c', 'channel');

        $chans = [
            'orders' => '訂單通知',
            'ads' => 'Ads 每日報表',
            'ads_strategy' => 'Ads 策略分析',
            'compliance' => '合規稽核',
        ];

        $stats = [
            Stat::make('Discord 推送總數', number_format($total))
                ->description($failed > 0 ? "⚠️ 失敗 {$failed} 則" : '全數送達')
                ->descriptionIcon($failed > 0 ? 'heroicon-m-exclamation-triangle' : 'heroicon-m-check-circle')
                ->color($failed > 0 ? 'danger' : 'success'),
        ];

        foreach ($chans as $slug => $label) {
            $stats[] = Stat::make($label, number_format((int) ($byChannel[$slug] ?? 0)))
                ->description($slug)
                ->color('info');
        }

        return $stats;
    }

    private function resolveRange(): array
    {
        $start = $this->pageFilters['startDate'] ?? null;
        $end = $this->pageFilters['endDate'] ?? null;

        $start = $start ? Carbon::parse($start)->startOfDay() : now()->subDays(29)->startOfDay();
        $end = $end ? Carbon::parse($end)->endOfDay() : now()->endOfDay();

        return [$start, $end];
    }
}
