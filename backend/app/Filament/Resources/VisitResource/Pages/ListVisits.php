<?php

namespace App\Filament\Resources\VisitResource\Pages;

use App\Filament\Resources\VisitResource;
use App\Models\Visit;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Carbon;

class ListVisits extends ListRecords
{
    protected static string $resource = VisitResource::class;

    public function getTitle(): string
    {
        $day = $this->focusDate();
        if ($day->isToday()) return '當日流量';
        return '流量紀錄 · ' . $day->format('Y-m-d');
    }

    protected function getHeaderWidgets(): array
    {
        return [
            \App\Filament\Widgets\VisitStatsWidget::class,
        ];
    }

    public function getSubheading(): ?string
    {
        // Stats reflect the table filter's selected date (defaults to today).
        // Uses whereBetween + Carbon startOfDay/endOfDay so the window is
        // timezone-safe — whereDate() would drift when the DB session tz
        // differs from APP_TIMEZONE.
        $day = $this->focusDate();
        $start = $day->copy()->startOfDay();
        $end = $day->copy()->endOfDay();

        $uv = Visit::whereBetween('visited_at', [$start, $end])
            ->where('referer_source', '!=', 'bot')
            ->distinct('visitor_id')->count('visitor_id');
        $pv = Visit::whereBetween('visited_at', [$start, $end])
            ->where('referer_source', '!=', 'bot')
            ->count();
        // Keep bots visible in the breakdown so admins can still see crawler
        // activity at a glance — just not in the headline UV/PV numbers.
        $bySource = Visit::whereBetween('visited_at', [$start, $end])
            ->selectRaw('referer_source, COUNT(*) as c')
            ->groupBy('referer_source')
            ->pluck('c', 'referer_source')
            ->toArray();

        $sourceLabels = [
            'direct' => '直接',
            'google' => 'Google',
            'google_ads' => 'GAds',
            'facebook' => 'FB',
            'instagram' => 'IG',
            'line' => 'LINE',
            'bing' => 'Bing',
            'email' => 'Email',
            'other' => '其他',
            'ai_referral' => 'AI引薦',
            'bot' => 'Bot',
        ];
        $parts = [];
        foreach ($sourceLabels as $k => $label) {
            if (! empty($bySource[$k])) $parts[] = "{$label} {$bySource[$k]}";
        }

        $dayLabel = $day->isToday() ? '今日' : $day->format('Y-m-d');
        return sprintf('%s UV %d · PV %d · %s', $dayLabel, $uv, $pv, $parts ? implode('　', $parts) : '—');
    }

    /**
     * Read the selected date from the table `date` filter. Defaults to today
     * when the filter is cleared or the value is invalid.
     */
    private function focusDate(): Carbon
    {
        $value = $this->tableFilters['date']['value'] ?? null;
        if ($value) {
            try { return Carbon::parse($value); } catch (\Throwable) {}
        }
        return Carbon::today();
    }
}
